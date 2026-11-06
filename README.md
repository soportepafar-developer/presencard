# Presencard

Tarjetas de presentación digitales. Cada tarjeta tiene una URL pública única (`/c/{hash}`) que muestra nombre y cargo al compartirla por WhatsApp o redes (Open Graph). Los usuarios inician sesión en un panel para gestionarlas.

## Usuarios y permisos

| | Administrador | Usuario |
|---|---|---|
| Tarjetas | Ve y gestiona **todas** (con su responsable) | Ve y gestiona **solo las suyas** (una ajena responde 404) |
| Usuarios | Crea, edita (nombre, correo, rol), restablece contraseña, desactiva/reactiva y elimina | — |
| Perfil | Edita su nombre y correo, y cambia su contraseña | Igual |

Reglas: siempre debe existir al menos un administrador activo; nadie puede eliminarse, desactivarse ni cambiarse el rol a sí mismo desde la gestión de usuarios; al eliminar a un usuario sus tarjetas pasan al administrador que lo elimina. El administrador define la contraseña inicial (puede generarla desde el formulario) y no se depende de correo. Cambiar la contraseña cierra las demás sesiones del usuario; desactivar una cuenta cierra su sesión y le impide volver a entrar.

| Carpeta | Contenido |
|---|---|
| `back-presencard/` | API en PHP 7.4 + Symfony 5.2.\*, Arquitectura Hexagonal (Dominio / Aplicación / Infraestructura) |
| `front-presencard/` | Angular 21 (standalone), pruebas con Karma/Jasmine |
| `docker/`, `docker-compose.yml` | Entorno de desarrollo: PHP 7.4-fpm, nginx y MySQL 8 |

## Colores y Telegram

Cada tarjeta puede llevar su propia paleta: al crearla o editarla se elige una paleta sugerida o se escriben dos colores (`#RRGGBB`): el **principal** (cabecera, íconos, textos destacados) y el de **acento** (botón de WhatsApp, marco del avatar, bloque inferior). El resto de tonos y el color del texto (blanco u oscuro, según el contraste) se derivan solos en `shared/utils/palette.ts`. Sin colores guardados la tarjeta usa la paleta de Presencard. También admite el usuario de Telegram (`@usuario` o enlace `t.me`), que se agrega al `.vcf`.

## Enlace y código QR

En «Tarjetas», el botón del enlace (y el aviso al crear una tarjeta) abre un modal con la URL única, el botón de copiar y su **código QR**. El QR contiene solo la URL de la tarjeta y se genera en el navegador (librería `qrcode-generator`, que se descarga únicamente al abrir el modal). Está pensado para escanearse fácil con un móvil:

- **Pocos módulos:** con una URL corta (`…/c/` + 12 caracteres) sale una cuadrícula de 29×29. Se elige automáticamente la más pequeña posible (nivel de corrección M, o L si así baja de tamaño).
- **Nítido:** negro sobre blanco, zona de silencio de 4 módulos y cada módulo con un número entero de píxeles, sin suavizado.
- **Descarga en PNG** (≈1000 px, `qr-nombre-apellido.png`), listo para pantallas, WhatsApp o imprenta.

Un `APP_PUBLIC_URL` más corto da un QR aún más sencillo: la longitud de la URL es lo que determina el tamaño de la cuadrícula.

## Contacto (.vcf)

La tarjeta pública incluye el botón **Guardar contacto**, que descarga un archivo vCard 3.0 (`GET /api/public/cards/{hash}/vcard.vcf`) listo para añadirse a la agenda del móvil: nombre, empresa (`ORG`), cargo (`TITLE`), teléfono, WhatsApp (si es otro número), correo, el enlace de la tarjeta y el Instagram. Los datos que falten simplemente no se incluyen. El formato (escapes, saltos de línea CRLF, plegado a 75 octetos sin partir caracteres) está cubierto por pruebas y se validó con el lector de contactos de macOS.

## Cómo funciona la vista pública y el SEO

`GET /c/{hash}` lo atiende **Symfony**: busca la tarjeta por `hash_id` (índice único), toma el `index.html` compilado de Angular, le inyecta `<title>` y las etiquetas `og:*` con el nombre y cargo de la persona, y embebe los datos en un `<script type="application/json">`. Los rastreadores de WhatsApp/redes (que no ejecutan JS) ven las etiquetas; Angular arranca después y pinta la tarjeta sin pedir los datos otra vez. Un hash inexistente responde 404 con `noindex`.

Para que esto funcione, el build de Angular debe estar en `back-presencard/public/app/` (ver "Build").

## Desarrollo

Requiere Docker Desktop iniciado.

```bash
docker compose up -d                      # php, nginx (http://localhost:8090) y mysql
docker compose run --rm --no-deps php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:create-admin correo@dominio.com   # pide la contraseña (en producción no hay Terminal: el administrador inicial viene en el SQL)
```

Frontend con recarga en vivo (usa proxy a `http://localhost:8090` para `/api`):

```bash
cd front-presencard
npm install
npm start                                  # http://localhost:4300
```

Rutas: `/login`, `/panel/cards` (tarjetas), `/panel/users` (solo administradores), `/panel/profile` (perfil), `/c/{hash}` (tarjeta pública).

API: `/api/cards` (tarjetas), `/api/users` (administradores), `/api/me` y `/api/me/password` (perfil), `/api/login`, `/api/logout`, `/api/public/cards/{hash}`.

### Pruebas

```bash
docker compose run --rm --no-deps php vendor/bin/phpunit                  # backend
cd front-presencard && npx ng test --watch=false --browsers=ChromeHeadless   # frontend
```

Karma necesita un navegador basado en Chromium. Si no tienes Chrome, apunta `CHROME_BIN` al ejecutable (p. ej. Brave: `CHROME_BIN="/Applications/Brave Browser.app/Contents/MacOS/Brave Browser"`).

### Build

```bash
cd front-presencard
npm run build:deploy     # compila con base href /app/ y copia a back-presencard/public/app (sin borrar lo anterior)
```

Los nombres de los archivos de cada pantalla llevan un hash que cambia en cada compilación, por eso el despliegue **no borra los archivos de la versión anterior** (solo poda los de más de 14 días): una pestaña que siga abierta con la versión vieja todavía puede cargar sus pantallas. Si aun así falla la carga de una pantalla, la app recarga sola la página en esa URL. **En cPanel, sube la carpeta `public/app` encima de la existente en vez de reemplazarla.**

Con el build desplegado, `http://localhost:8090/c/{hash}` y `/panel` los sirve Symfony con el shell de Angular.

## Producción (cPanel, subcarpeta `/presencard`)

La app puede vivir en la raíz de un dominio (desarrollo) o en una subcarpeta (`https://pafar.net/presencard`): Symfony publica el prefijo en una `<meta name="presencard-root">` y Angular construye con él la URL de la API y de las rutas, así que **una sola compilación sirve para ambos casos**. La cookie de sesión se llama `presencard_session` y usa como ruta el prefijo, para no mezclarse con el sitio principal.

### Generar el paquete

```bash
docker compose up -d                 # el script usa el MySQL de desarrollo para generar el SQL
./scripts/build-release.sh           # lee release/production.env (copia release/production.env.ejemplo)
```

Deja en `release/out/entrega/` tres archivos:

| Archivo | Para qué |
|---|---|
| `presencard.zip` | Una sola carpeta `presencard/` (Front en `app/`, Back en `back/`, `index.php`, `.htaccess`, `diagnostico.php`). Se extrae dentro de `public_html`; no hay que mover nada. |
| `base-de-datos.sql` | Tablas + administrador inicial; se importa en phpMyAdmin. |
| `LEEME-DESPLIEGUE.md` | Guía de 6 pasos para principiantes. |

La configuración real (BD, secreto, URL) va en `back/.env.local.php`, un archivo PHP que devuelve un array: si alguien lo pidiera por URL, PHP lo ejecuta y no muestra nada. El código queda protegido por varias capas: `back/.htaccess` (`Require all denied`), la regla `RewriteRule ^back(/|$)` del `.htaccess` principal y el bloqueo de archivos ocultos. `diagnostico.php?clave=DIAG_KEY` es una página temporal (se borra al terminar) que marca en ✔/▲/✖ PHP, extensiones, permisos, BD y seguridad, sin mostrar contraseñas.

`release/production.env` guarda las credenciales, `DIAG_KEY` y el administrador inicial (contraseña temporal aleatoria): **no se sube ni se comparte**. El script comprueba que el SQL sea portable a MariaDB y que, importado en una base vacía, el esquema coincida con el mapeo de Doctrine.

### Probar el paquete como en cPanel

```bash
./release/smoke/prepare.sh           # copia el paquete, importa el SQL en una BD temporal
docker compose -f release/smoke/docker-compose.smoke.yml -p presencard-smoke up -d --build
# http://localhost:8091/presencard  (Apache + .htaccess reales, PHP 7.4; extrae entrega/presencard.zip tal cual)
```

### Actualizar

Los archivos de `app/` llevan un hash que cambia en cada compilación: **sube `app/` encima de la carpeta existente sin vaciarla**, así las pestañas abiertas con la versión anterior siguen funcionando (y si una pantalla no carga, la app recarga sola en esa URL). Antes de extraer el ZIP nuevo, guarda una copia de `back/.env.local.php`. El resto de pasos está en la guía del paquete.

## Seguridad

- **Contraseñas**: bcrypt (portable entre Docker y cPanel), mínimo 8 caracteres. Para cambiarla: «Mi perfil», o `app:create-admin <correo>` (sobre un correo existente restablece su contraseña, lo deja como administrador y activo: sirve también para recuperar el acceso).
- **Límite de intentos de login**: 5 fallos con el mismo correo desde la misma conexión (o 30 desde una misma IP) bloquean el ingreso 15 minutos, aunque luego se escriba la clave correcta (429 + `Retry-After`). Los contadores viven en `var/throttle`, que no se borra al vaciar `var/cache`; para desbloquear de inmediato se vacía esa carpeta. Las caídas de la base de datos (503) y las cuentas desactivadas no cuentan como intento fallido.
- **Sesión**: cookie `presencard_session` (HttpOnly, SameSite=Lax, ruta = prefijo de la app) y 8 horas de inactividad; los archivos de sesión están en `var/sessions`.
- **Privacidad**: las páginas públicas de tarjeta, el `.vcf` y la API pública llevan `noindex, nofollow`, así que los datos de contacto no aparecen en buscadores; se llega por enlace o QR. No se usa `robots.txt` con `Disallow` porque bloquearía la vista previa de Facebook.
- **Vista previa de WhatsApp/redes**: `og:title` (nombre), `og:description` (cargo · empresa) e `og:image` (`front-presencard/public/og-image.png`, 1200×630; se regenera con `scripts/og-image/generate.sh` o se reemplaza por el logo real). Se activa con `APP_OG_IMAGE=/app/og-image.png` (en producción, en `back/.env.local.php`).
- La API exige `Content-Type: application/json` en peticiones con cuerpo y la sesión usa cookie `SameSite=Lax`; las etiquetas `og:*` y el JSON embebido se escapan en el servidor.
- Si el hosting estuviera detrás de un proxy/CDN (p. ej. Cloudflare), declarar `trusted_proxies` para que el límite de intentos use la IP real del visitante.
