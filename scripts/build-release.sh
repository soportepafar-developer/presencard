#!/usr/bin/env bash
#
# Construye la entrega de producción de Presencard para cPanel: UNA sola carpeta (presencard/) con el Front y el Back.
#
#   ./scripts/build-release.sh
#
# Lee release/production.env (credenciales, URL pública...) y deja en release/out/entrega/:
#   presencard.zip           contiene la carpeta presencard/ -> se extrae dentro de public_html
#   base-de-datos.sql        tablas + administrador inicial -> se importa en phpMyAdmin (base VACÍA)
#   reparar-actualizar.sql   versión repetible para bases que ya tienen tablas (no borra datos)
#   LEEME-DESPLIEGUE.md      guía paso a paso
#
# Requisitos: Docker en marcha con el entorno de desarrollo (`docker compose up -d`), Node/npm y rsync.
# No modifica el vendor ni la base de datos de desarrollo (usa una copia y bases temporales que borra al final).

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CONF="$ROOT/release/production.env"
OUT="$ROOT/release/out"
STAGE="$OUT/stage"
ENTREGA="$OUT/entrega"
SITE="$STAGE/presencard"       # lo que se sube a public_html/presencard
BACK="$SITE/back"
BUILD_DB="presencard_release_build"
CHECK_DB="presencard_release_check"

[ -f "$CONF" ] || { echo "Falta $CONF (ver release/production.env.ejemplo)." >&2; exit 1; }
# shellcheck disable=SC1090
set -a; . "$CONF"; set +a

for var in DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD DB_SERVER_VERSION PUBLIC_URL COOKIE_PATH ADMIN_EMAIL ADMIN_NAME ADMIN_TEMP_PASSWORD APP_SECRET DIAG_KEY; do
  [ -n "${!var:-}" ] || { echo "Falta $var en $CONF" >&2; exit 1; }
done

cd "$ROOT"
dc() { docker compose "$@"; }
step() { printf '\n==> %s\n' "$1"; }

step "1/8 Compilando Angular (producción, base /app/)"
( cd front-presencard && rm -rf dist && npx ng build --base-href /app/ >/dev/null )
[ -f front-presencard/dist/front-presencard/browser/index.html ] || { echo "El build de Angular no generó index.html" >&2; exit 1; }

step "2/8 Preparando la carpeta de salida"
: "${OUT:?}" "${STAGE:?}" "${ENTREGA:?}"
rm -rf "$STAGE" "$ENTREGA" "$OUT/presencard-produccion" "$OUT/presencard-produccion.zip"
mkdir -p "$SITE/app" "$BACK" "$ENTREGA"

step "3/8 Copiando el backend (sin pruebas ni archivos de desarrollo)"
rsync -a \
  --exclude='/tests/' --exclude='/bin/phpunit' --exclude='/public/' --exclude='/vendor/' --exclude='/var/' \
  --exclude='/phpunit.xml.dist' --exclude='/.phpunit.result.cache' --exclude='/.env' --exclude='/.env.test' --exclude='/.env.local' --exclude='/.gitignore' \
  --exclude='.DS_Store' \
  back-presencard/ "$BACK/"
# var/sessions (sesiones de 8 h) y var/throttle (contadores del límite de intentos de login) deben existir y ser escribibles.
for dir in cache log sessions throttle; do
  mkdir -p "$BACK/var/$dir"
  touch "$BACK/var/$dir/.gitkeep"
done

step "4/8 Dependencias de producción (composer install --no-dev, sobre una copia del vendor)"
rsync -a back-presencard/vendor/ "$BACK/vendor/"
docker run --rm -v "$BACK:/work" -w /work presencard-php \
  composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

step "5/8 Configuración (back/.env.local.php), protección de back/ y archivos web"
cat > "$BACK/.env" <<'ENV'
# Valores por defecto neutros. En producción la configuración real está en .env.local.php (que tiene prioridad).
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=definir-en-env-local-php
DATABASE_URL="mysql://usuario:clave@localhost:3306/base?serverVersion=mariadb-10.11.19&charset=utf8mb4"
APP_PUBLIC_URL=https://example.invalid
ENV
# .env.local.php: un archivo PHP que devuelve la configuración. Si alguien lo pidiera por internet, PHP lo ejecuta y
# no muestra nada (a diferencia de un .env de texto plano): las contraseñas no se filtran ni aunque fallara el .htaccess.
python3 - "$BACK/.env.local.php" <<'PY'
import os, sys
from urllib.parse import quote
e = os.environ
url = "mysql://{u}:{p}@{h}:{port}/{db}?serverVersion={v}&charset=utf8mb4".format(
    u=quote(e["DB_USER"], safe=""), p=quote(e["DB_PASSWORD"], safe=""), h=e["DB_HOST"],
    port=e["DB_PORT"], db=e["DB_NAME"], v=e["DB_SERVER_VERSION"])

def php(value):
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"

lines = [
    ("APP_ENV", "prod", "Modo producción."),
    ("APP_DEBUG", "0", "Depuración apagada."),
    ("APP_SECRET", e["APP_SECRET"], "Secreto de Symfony (firma interna). No lo cambies sin motivo."),
    ("DATABASE_URL", url, "Conexión a la base de datos: usuario:CONTRASEÑA@servidor/base. Si cambias la contraseña en cPanel, edita SOLO esta línea\n    // (los símbolos @ / : ? de la contraseña se escriben como %40 %2F %3A %3F)."),
    ("APP_PUBLIC_URL", e["PUBLIC_URL"], "URL pública definitiva: de aquí salen los enlaces compartidos, los QR y la vista previa de WhatsApp."),
    ("APP_COOKIE_PATH", e["COOKIE_PATH"], "Cookie de sesión limitada a esta subcarpeta."),
    ("SPA_INDEX_RELATIVE", "../app/index.html", "Pantalla principal de la aplicación (carpeta app/), relativa a la carpeta back."),
    ("APP_OG_IMAGE", "/app/og-image.png", "Imagen de la vista previa de WhatsApp/redes (1200x630), relativa a la URL pública. Vacío = sin imagen."),
]
out = ["<?php", "", "// CONFIGURACIÓN DE PRODUCCIÓN de Presencard. Contiene contraseñas: no la compartas.", "// Es un archivo PHP: si se abriera desde internet no mostraría nada.", "", "return ["]
for key, value, comment in lines:
    out.append(f"    // {comment}")
    out.append(f"    {php(key)} => {php(value)},")
out += ["];", ""]
open(sys.argv[1], "w").write("\n".join(out))
os.chmod(sys.argv[1], 0o600)
PY
cp scripts/templates/back.htaccess "$BACK/.htaccess"
cp back-presencard/public/.htaccess "$SITE/.htaccess"
cp scripts/templates/index.php "$SITE/index.php"
sed "s|{{DIAG_KEY}}|$DIAG_KEY|g" scripts/templates/diagnostico.php > "$SITE/diagnostico.php"
rsync -a front-presencard/dist/front-presencard/browser/ "$SITE/app/"
cp scripts/templates/app.htaccess "$SITE/app/.htaccess"
[ -f "$SITE/app/og-image.png" ] || { echo "Falta og-image.png en el build (front-presencard/public/og-image.png)" >&2; exit 1; }

step "6/8 Base de datos: generando base-de-datos.sql (estructura final + administrador inicial)"
ROOT_URL="mysql://root:root@db:3306"
dc exec -T db mysql -uroot -proot -e "DROP DATABASE IF EXISTS $BUILD_DB; CREATE DATABASE $BUILD_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
dc run --rm --no-deps -e DATABASE_URL="$ROOT_URL/$BUILD_DB?serverVersion=8.0&charset=utf8mb4" php \
  php bin/console doctrine:migrations:migrate --no-interaction >/dev/null 2>&1
ADMIN_HASH="$(dc run --rm --no-deps -e PW="$ADMIN_TEMP_PASSWORD" php \
  php -r 'echo password_hash(getenv("PW"), PASSWORD_BCRYPT, ["cost" => 12]);' 2>/dev/null)"
case "$ADMIN_HASH" in '$2y$'*) ;; *) echo "No se pudo generar el hash del administrador" >&2; exit 1 ;; esac

SQL="$ENTREGA/base-de-datos.sql"
{
  echo "-- Presencard · base de datos inicial (estructura final + administrador). Importar en phpMyAdmin."
  echo "-- Compatible con MariaDB 10.2+ y MySQL 5.7+. Importar sobre una base de datos VACÍA."
  echo "SET NAMES utf8mb4;"
  echo
  dc exec -T db mysqldump -uroot -proot --compact --no-data --skip-add-drop-table --no-tablespaces "$BUILD_DB" 2>/dev/null
  echo
  echo "-- Versiones de migración ya aplicadas (para que futuras migraciones no repitan estas)."
  dc exec -T db mysqldump -uroot -proot --compact --no-create-info --no-tablespaces "$BUILD_DB" doctrine_migration_versions 2>/dev/null
  echo
  echo "-- Administrador inicial. La contraseña temporal se entrega aparte; hay que cambiarla al entrar."
  printf "INSERT INTO \`users\` (\`email\`, \`full_name\`, \`password_hash\`, \`role\`, \`active\`, \`created_at\`, \`updated_at\`) VALUES ('%s', '%s', '%s', 'ROLE_ADMIN', 1, NOW(), NOW());\n" \
    "$ADMIN_EMAIL" "$ADMIN_NAME" "$ADMIN_HASH"
} > "$SQL"

# Limpieza para MariaDB/phpMyAdmin: fuera los comentarios condicionales de MySQL y utf8mb3 (MariaDB < 10.6 no lo reconoce)
sed -i.bak -e '/^\/\*!/d' -e 's/utf8mb3_unicode_ci/utf8mb4_unicode_ci/g' -e 's/utf8mb3/utf8mb4/g' "$SQL" && rm -f "$SQL.bak"

# Comprobaciones de compatibilidad con MariaDB / phpMyAdmin
for bad in 'utf8mb4_0900' 'utf8mb3' 'CREATE DATABASE' 'DEFINER' '^USE ' '^/\*!'; do
  if grep -qE "$bad" "$SQL"; then echo "El SQL contiene '$bad': no es portable" >&2; exit 1; fi
done

# Prueba de importación: se carga el SQL en una base vacía y se valida contra el mapeo de Doctrine
dc exec -T db mysql -uroot -proot -e "DROP DATABASE IF EXISTS $CHECK_DB; CREATE DATABASE $CHECK_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
dc exec -T db mysql -uroot -proot "$CHECK_DB" < "$SQL" 2>/dev/null
dc run --rm --no-deps -e DATABASE_URL="$ROOT_URL/$CHECK_DB?serverVersion=8.0&charset=utf8mb4" php \
  php bin/console doctrine:schema:validate 2>&1 | grep -E "in sync|ERROR|differ" || true
dc exec -T db mysql -uroot -proot -N -e "SELECT CONCAT('admin importado: ', email, ' (', role, ')') FROM $CHECK_DB.users;" 2>/dev/null
dc exec -T db mysql -uroot -proot -e "DROP DATABASE $BUILD_DB; DROP DATABASE $CHECK_DB;" 2>/dev/null
python3 scripts/make-repair-sql.py "$SQL" "$ENTREGA/reparar-actualizar.sql"

step "7/8 Guía de despliegue"
sed -e "s|{{PUBLIC_URL}}|$PUBLIC_URL|g" -e "s|{{DB_NAME}}|$DB_NAME|g" -e "s|{{DB_USER}}|$DB_USER|g" -e "s|{{ADMIN_EMAIL}}|$ADMIN_EMAIL|g" \
  scripts/templates/LEEME-DESPLIEGUE.md > "$ENTREGA/LEEME-DESPLIEGUE.md"

step "8/8 Comprimiendo presencard.zip"
find "$STAGE" -name '.DS_Store' -delete
( cd "$STAGE" && zip -qr -X "$ENTREGA/presencard.zip" presencard )

echo
echo "Listo. Archivos de la entrega:"
echo "  $ENTREGA/presencard.zip       ($(du -h "$ENTREGA/presencard.zip" | cut -f1))  -> extraer dentro de public_html"
echo "  $ENTREGA/base-de-datos.sql    -> importar en phpMyAdmin"
echo "  $ENTREGA/LEEME-DESPLIEGUE.md  -> guía"
