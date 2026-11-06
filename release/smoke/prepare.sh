#!/usr/bin/env bash
# Extrae release/out/entrega/presencard.zip en release/smoke/site (como en public_html), ajusta la conexión y la URL
# pública al entorno de pruebas (BD temporal del Docker de desarrollo, http://localhost:8091/presencard) e importa
# base-de-datos.sql como lo haría phpMyAdmin. Es lo que el usuario subirá, sin tocar nada más.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
ENTREGA="$ROOT/release/out/entrega"
SITE="$ROOT/release/smoke/site"

: "${SITE:?}"
rm -rf "$SITE"
mkdir -p "$SITE"
unzip -q "$ENTREGA/presencard.zip" -d "$SITE"

cd "$ROOT"
docker compose exec -T db mysql -uroot -proot -e "DROP DATABASE IF EXISTS presencard_smoke; CREATE DATABASE presencard_smoke CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
docker compose exec -T db mysql -uroot -proot presencard_smoke < "$ENTREGA/base-de-datos.sql" 2>/dev/null

# Solo cambian la conexión y la URL pública; todo lo demás es exactamente lo que se subirá a producción.
python3 - "$SITE/presencard/back/.env.local.php" <<'PY'
import re, sys
p = sys.argv[1]
s = open(p).read()
s = re.sub(r"'DATABASE_URL' => '[^']*'", "'DATABASE_URL' => 'mysql://root:root@db:3306/presencard_smoke?serverVersion=8.0&charset=utf8mb4'", s)
s = re.sub(r"'APP_PUBLIC_URL' => '[^']*'", "'APP_PUBLIC_URL' => 'http://localhost:8091/presencard'", s)
open(p, 'w').write(s)
PY
chmod -R a+rwX "$SITE/presencard/back/var"
echo "sitio de prueba listo en $SITE (presencard/ dentro de la raíz web)"
