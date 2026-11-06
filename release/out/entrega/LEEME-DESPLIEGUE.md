# Presencard · Cómo publicarlo en cPanel (paso a paso)

Quedará funcionando en **https://pafar.net/presencard**. Se hace igual que siempre: subes **una carpeta** a `public_html` (aquí viene comprimida en `presencard.zip`) y cargas la base de datos. No hace falta Terminal ni mover archivos entre carpetas.

## Qué recibiste

| Archivo | Para qué sirve |
|---|---|
| `presencard.zip` | Todo el proyecto en una carpeta llamada `presencard` (el Front y el Back adentro). Se extrae dentro de `public_html`. |
| `base-de-datos.sql` | Las tablas y el primer administrador. Se importa en phpMyAdmin. |
| `LEEME-DESPLIEGUE.md` | Esta guía. |

Además necesitas dos datos que te dieron por chat: la **clave del diagnóstico** y la **contraseña temporal** del administrador.

Tiempo estimado: 15 minutos.

---

## Paso 1 · Elegir la versión de PHP

cPanel → **MultiPHP Manager** (Administrador MultiPHP).

- Elige el dominio `pafar.net` y la versión **PHP 7.4, 8.0 u 8.1** si están en la lista (es lo más seguro). Si solo tienes 8.4, también funciona.
- Aviso: la versión se aplica a *todo* el dominio. Si en `public_html` tienes otros proyectos que dependen de otra versión, **no cambies la del dominio**: en su lugar, déjala como está y sigue; el diagnóstico (Paso 5) te dirá si algo falta.

En **Seleccionar versión de PHP → Extensiones**, comprueba que estén activas: `pdo_mysql`, `mbstring`, `xml` (incluye dom y simplexml), `ctype`, `iconv`, `json`, `tokenizer`.

**Qué debes ver:** la versión elegida junto a `pafar.net`.

## Paso 2 · Cargar la base de datos

1. cPanel → **phpMyAdmin**.
2. En la lista de la izquierda haz clic en **pafarnet_presencard** (debe estar vacía).
3. Arriba, pestaña **Importar** → *Seleccionar archivo* → elige `base-de-datos.sql` → baja y pulsa **Importar** (o *Continuar*).

**Qué debes ver:** un mensaje verde y, a la izquierda, 3 tablas: `doctrine_migration_versions`, `presencards`, `users`.

Si da error: confirma que elegiste la base **pafarnet_presencard** y que estaba vacía. El usuario **pafarnet_presencard_user** debe estar agregado a esa base con *todos los privilegios* (cPanel → **Bases de datos MySQL** → «Agregar usuario a la base de datos»).

## Paso 3 · Subir el proyecto

1. cPanel → **Administrador de archivos**.
2. Arriba a la derecha, **Configuración** → marca **Mostrar archivos ocultos (dotfiles)** → Guardar. (Importante: `.htaccess` y otros archivos empiezan con punto.)
3. Entra a la carpeta **public_html**.
4. Botón **Cargar** (Upload) → sube `presencard.zip` → vuelve al administrador.
5. Selecciona `presencard.zip` → botón **Extraer** (Extract) → confirma que sea en `public_html`.
6. Verás la carpeta **public_html/presencard**. Ya puedes borrar `presencard.zip` de `public_html`.

**Qué debes ver** dentro de `public_html/presencard`: `index.php`, `diagnostico.php`, `.htaccess`, la carpeta `app` y la carpeta `back` (que a su vez tiene `.htaccess`, `.env.local.php`, `src`, `vendor`, `var`…).

## Paso 4 · Permisos

En el Administrador de archivos, dentro de `public_html/presencard/back`:

- Clic derecho en la carpeta **var** → **Change Permissions** → pon **755** (si luego el diagnóstico dice que no se puede escribir, usa **775**) y marca *Recursive* si aparece.
- Clic derecho en **.env.local.php** → Change Permissions → **640** (o 600).

## Paso 5 · Diagnóstico (te dice si todo está bien)

Abre en el navegador:

`https://pafar.net/presencard/diagnostico.php?clave=LA_CLAVE_QUE_TE_DIERON`

Verás una lista con ✔ verde, ▲ amarillo y ✖ rojo. Cada línea roja explica qué hacer. Corrige y recarga hasta que no haya rojos. Revisa: PHP, extensiones, archivos, permisos, base de datos y seguridad.

**Cuando todo esté en verde: BORRA `diagnostico.php`** (Administrador de archivos → clic derecho → Delete). No debe quedarse en el servidor.

## Paso 6 · Entrar y dejarlo listo

1. Abre **https://pafar.net/presencard/login**.
2. Usuario: **admin@pafar.net** · contraseña: la temporal que te dieron.
3. Arriba a la derecha, tu nombre → **Mi perfil → Cambiar contraseña**. Pon una propia (mínimo 8 caracteres). Hazlo ya.
4. **Usuarios → Nuevo usuario** para crear a tu equipo (el botón «Generar» crea una clave temporal segura).
5. Crea una tarjeta de prueba: ábrela en el celular, escanea su QR y toca **Guardar contacto**. Pega el enlace en WhatsApp para ver la vista previa.

**¡Listo!** Después, haz estas dos cosas el mismo día:
- Cambia la **contraseña de la base de datos** (cPanel → Bases de datos MySQL → «Cambiar contraseña») y edita **una sola línea** en `presencard/back/.env.local.php` (la que dice `DATABASE_URL`; la contraseña va entre `:` y `@`; si tiene símbolos, escríbelos como `@`→`%40`, `/`→`%2F`, `:`→`%3A`, `?`→`%3F`).
- Activa **Force HTTPS Redirect** (cPanel → Dominios), si no lo está.

---

## Cómo se comporta (para que no te sorprenda)

- **Intentos de ingreso**: tras **5 intentos fallidos** con el mismo correo (o 30 desde una misma conexión) el ingreso se bloquea **15 minutos**, aunque después se escriba la clave correcta. Si le pasa a un usuario legítimo, solo tiene que esperar. Para desbloquear de inmediato: vacía la carpeta `presencard/back/var/throttle`.
- **Sesión**: se mantiene abierta hasta **8 horas de inactividad**.
- **Google**: las tarjetas llevan `noindex`; no aparecen en buscadores, solo se llega a ellas por enlace o QR. La vista previa de WhatsApp sigue funcionando.
- **Vista previa**: muestra el nombre, el cargo/empresa y una imagen. Para cambiar la imagen, reemplaza `presencard/app/og-image.png` por otra de 1200×630 px y menos de 300 KB.

## Si algo falla

| Síntoma | Qué hacer |
|---|---|
| Error 500 o página en blanco | Abre el diagnóstico (Paso 5; si ya lo borraste, súbelo de nuevo desde el ZIP). Revisa también el archivo `error_log` (en `public_html/presencard` o cPanel → *Métricas → Errores*). |
| «No se pudo verificar tu acceso en este momento» | Falla la conexión con la base de datos: revisa `DATABASE_URL` en `back/.env.local.php` y los privilegios del usuario sobre la base. |
| «Demasiados intentos fallidos» | Esperar 15 minutos, o vaciar `back/var/throttle`. |
| Se ve el login sin estilos / página casi vacía | Falta la carpeta `app` o el archivo oculto `.htaccess`: vuelve a extraer el ZIP con «Mostrar archivos ocultos» activado. |
| Al recargar una página interna sale 404 | Falta el archivo oculto `public_html/presencard/.htaccess`. |
| Los enlaces o QR salen con otra dirección | Corrige `APP_PUBLIC_URL` en `back/.env.local.php` y vacía `back/var/cache`. |

## Actualizar en el futuro

1. Exporta la base de datos (phpMyAdmin → Exportar) como respaldo.
2. Extrae el nuevo `presencard.zip` dentro de `public_html` aceptando **reemplazar** archivos. Después de extraer, **verifica que no se haya pisado tu configuración**: si `back/.env.local.php` volvió a tener valores de ejemplo, restaura la copia que guardaste antes (haz una copia de ese archivo **antes** de actualizar).
3. Si la actualización incluye un `.sql`, impórtalo en phpMyAdmin.
4. Vacía el contenido de `back/var/cache`. **No** borres `back/var/sessions` ni `back/var/throttle`.
5. Para volver atrás: restaura los archivos anteriores y el `.sql` exportado.
