<?php

/*
 * Presencard · página de DIAGNÓSTICO (temporal).
 *
 * Muestra en verde / amarillo / rojo si el servidor está listo para Presencard: PHP, extensiones, archivos,
 * permisos, configuración, base de datos y seguridad. No muestra contraseñas.
 *
 * Ábrela así:  https://TU-DOMINIO/presencard/diagnostico.php?clave=LA_CLAVE
 * Cuando todo esté en verde, BORRA este archivo (diagnostico.php).
 */

const DIAG_KEY = '{{DIAG_KEY}}';

// Nunca mostrar errores de PHP en pantalla (podrían revelar rutas) ni avisos de obsolescencia de versiones nuevas.
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');

$given = isset($_GET['clave']) && is_string($_GET['clave']) ? $_GET['clave'] : '';

if (!hash_equals(DIAG_KEY, $given)) {
    http_response_code(403);
    exit;
}

$root = __DIR__;
$back = $root . '/back';
$checks = [];

function add(array &$checks, string $group, string $status, string $title, string $hint = ''): void
{
    $checks[] = ['group' => $group, 'status' => $status, 'title' => $title, 'hint' => $hint];
}

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/** Pide una URL de este mismo sitio. Devuelve [código, cuerpo] o null si no se pudo hacer la petición. */
function fetchUrl(string $url): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $body === false || $code === 0 ? null : [$code, (string) $body];
    }

    if (ini_get('allow_url_fopen')) {
        $context = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true, 'follow_location' => 0], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $body = @file_get_contents($url, false, $context);

        if ($body === false || !isset($http_response_header[0]) || !preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            return null;
        }

        return [(int) $m[1], (string) $body];
    }

    return null;
}

// ---------------------------------------------------------------- PHP
$phpVersion = PHP_VERSION;
if (version_compare($phpVersion, '7.4.0', '<')) {
    add($checks, 'PHP', 'fail', "Versión de PHP $phpVersion (se necesita 7.4 o superior)", 'cPanel → MultiPHP Manager: elige PHP 7.4, 8.0 o 8.1.');
} elseif (version_compare($phpVersion, '8.2.0', '>=')) {
    add($checks, 'PHP', 'warn', "Versión de PHP $phpVersion: funciona, pero Symfony 5.2 genera avisos internos (ocultos)", 'Si tu cPanel ofrece PHP 7.4, 8.0 u 8.1 (MultiPHP Manager), es la opción más segura.');
} else {
    add($checks, 'PHP', 'ok', "Versión de PHP $phpVersion");
}

foreach (['pdo_mysql', 'mbstring', 'dom', 'SimpleXML', 'xml', 'ctype', 'iconv', 'json', 'tokenizer'] as $extension) {
    if (extension_loaded($extension)) {
        add($checks, 'PHP', 'ok', "Extensión $extension");
    } else {
        add($checks, 'PHP', 'fail', "Falta la extensión $extension", 'cPanel → Seleccionar versión de PHP → Extensiones: actívala.');
    }
}

// ---------------------------------------------------------------- Archivos
foreach (['index.php', '.htaccess', 'app/index.html', 'back/.htaccess', 'back/src/Kernel.php', 'back/vendor/autoload.php', 'back/.env.local.php'] as $file) {
    if (is_file($root . '/' . $file)) {
        add($checks, 'Archivos', 'ok', $file);
    } else {
        add($checks, 'Archivos', 'fail', "Falta $file", 'Vuelve a extraer presencard.zip dentro de public_html, con «Mostrar archivos ocultos» activado.');
    }
}

if (!is_file($root . '/app/og-image.png')) {
    add($checks, 'Archivos', 'warn', 'Falta app/og-image.png (la vista previa de WhatsApp saldrá sin imagen)', 'Sube una imagen de 1200×630 px con ese nombre dentro de la carpeta app.');
}

// ---------------------------------------------------------------- Permisos
foreach (['var', 'var/cache', 'var/log', 'var/sessions', 'var/throttle'] as $dir) {
    $path = $back . '/' . $dir;

    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }

    $probe = $path . '/.diagnostico-' . bin2hex(random_bytes(3));
    $writable = is_dir($path) && @file_put_contents($probe, 'x') !== false;

    if ($writable) {
        @unlink($probe);
        add($checks, 'Permisos', 'ok', "back/$dir se puede escribir");
    } else {
        add($checks, 'Permisos', 'fail', "back/$dir no se puede escribir", 'Administrador de archivos → clic derecho en la carpeta → Change Permissions → 755 (o 775).');
    }
}

// ---------------------------------------------------------------- Configuración
$env = is_file($back . '/.env.local.php') ? @include $back . '/.env.local.php' : null;

if (!is_array($env)) {
    add($checks, 'Configuración', 'fail', 'No se pudo leer back/.env.local.php', 'Debe ser un archivo PHP que empieza con <?php y devuelve la configuración. Vuelve a subirlo desde el ZIP.');
    $env = [];
} else {
    add($checks, 'Configuración', ($env['APP_ENV'] ?? '') === 'prod' ? 'ok' : 'fail', 'Modo producción (APP_ENV=prod)', "En back/.env.local.php debe decir 'APP_ENV' => 'prod'.");
    add($checks, 'Configuración', (string) ($env['APP_DEBUG'] ?? '1') === '0' ? 'ok' : 'fail', 'Depuración desactivada (APP_DEBUG=0)', "En back/.env.local.php debe decir 'APP_DEBUG' => '0'.");
    add($checks, 'Configuración', strlen((string) ($env['APP_SECRET'] ?? '')) >= 32 ? 'ok' : 'fail', 'Secreto de la aplicación definido', "Falta 'APP_SECRET' (32 caracteres o más) en back/.env.local.php.");

    $publicUrl = (string) ($env['APP_PUBLIC_URL'] ?? '');
    $publicHost = (string) parse_url($publicUrl, PHP_URL_HOST);
    $currentHost = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($publicUrl === '') {
        add($checks, 'Configuración', 'fail', 'Falta la URL pública (APP_PUBLIC_URL)', "Debe ser la dirección final, p. ej. 'https://pafar.net/presencard'.");
    } elseif ($publicHost !== $currentHost) {
        add($checks, 'Configuración', 'warn', "La URL pública configurada ($publicUrl) no coincide con el dominio desde el que abriste esta página ($currentHost)", 'Los enlaces compartidos y los QR usan la URL configurada: corrígela en back/.env.local.php si no es la definitiva.');
    } else {
        add($checks, 'Configuración', 'ok', "URL pública: $publicUrl");
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    add($checks, 'Configuración', $isHttps ? 'ok' : 'warn', $isHttps ? 'Conexión segura (HTTPS)' : 'Estás usando HTTP sin cifrar', 'cPanel → Dominios → activa «Force HTTPS Redirect». La sesión necesita HTTPS en producción.');

    $spaIndex = $back . '/' . ($env['SPA_INDEX_RELATIVE'] ?? 'public/app/index.html');
    add($checks, 'Configuración', is_file($spaIndex) ? 'ok' : 'fail', 'La aplicación encuentra su pantalla principal (SPA_INDEX_RELATIVE)', "Debe apuntar a ../app/index.html en back/.env.local.php.");
}

// ---------------------------------------------------------------- Base de datos
$databaseUrl = (string) ($env['DATABASE_URL'] ?? '');

if (!extension_loaded('pdo_mysql')) {
    add($checks, 'Base de datos', 'fail', 'No se puede probar la base de datos sin la extensión pdo_mysql', 'Actívala (ver sección PHP).');
} elseif ($databaseUrl === '' || ($db = parse_url($databaseUrl)) === false || !isset($db['host'], $db['path'])) {
    add($checks, 'Base de datos', 'fail', 'Falta o es inválida la conexión (DATABASE_URL)', "Revisa la línea 'DATABASE_URL' de back/.env.local.php.");
} else {
    $user = urldecode((string) ($db['user'] ?? ''));
    $pass = urldecode((string) ($db['pass'] ?? ''));
    $name = ltrim((string) $db['path'], '/');

    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'] ?? 3306, $name),
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        add($checks, 'Base de datos', 'ok', 'Conexión con la base de datos ' . $name . ' (servidor ' . $pdo->query('SELECT VERSION()')->fetchColumn() . ')');

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach (['users', 'presencards', 'doctrine_migration_versions'] as $table) {
            if (in_array($table, $tables, true)) {
                add($checks, 'Base de datos', 'ok', "Tabla $table");
            } else {
                add($checks, 'Base de datos', 'fail', "Falta la tabla $table", 'phpMyAdmin → elige la base → Importar → base-de-datos.sql (la base debe estar vacía).');
            }
        }

        if (in_array('users', $tables, true)) {
            $admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'ROLE_ADMIN' AND active = 1")->fetchColumn();
            add($checks, 'Base de datos', $admins > 0 ? 'ok' : 'fail', $admins > 0 ? "Administradores activos: $admins" : 'No hay ningún administrador activo', 'Importa base-de-datos.sql: incluye el administrador inicial.');
        }
    } catch (PDOException $exception) {
        $message = str_replace([$pass, urlencode($pass)], '***', $exception->getMessage());
        $code = (int) $exception->getCode();
        $hint = [
            1045 => 'Usuario o contraseña de la base de datos incorrectos: revisa la línea DATABASE_URL de back/.env.local.php.',
            1049 => "La base de datos «$name» no existe: revisa su nombre completo (con el prefijo de cPanel).",
            1044 => 'El usuario no tiene permisos sobre esa base: cPanel → Bases de datos MySQL → «Agregar usuario a la base de datos» con TODOS los privilegios.',
            2002 => 'No se pudo conectar con el servidor de la base de datos (host). En cPanel suele ser «localhost».',
        ][$code] ?? 'Revisa los datos de DATABASE_URL en back/.env.local.php.';
        add($checks, 'Base de datos', 'fail', 'No se pudo conectar a la base de datos: ' . $message, $hint);
    }
}

// ---------------------------------------------------------------- Seguridad: el código y las claves no deben ser accesibles
$scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

foreach (['/back/composer.json' => 'el código (back/)', '/.env' => 'los archivos ocultos'] as $path => $label) {
    $result = fetchUrl($base . $path);

    if ($result === null) {
        add($checks, 'Seguridad', 'warn', "No se pudo comprobar desde el servidor que $label esté protegido", "Compruébalo tú: abre $base$path en el navegador; debe decir «Forbidden» o «Not Found», nunca mostrar contenido.");
    } elseif ($result[0] === 200) {
        add($checks, 'Seguridad', 'fail', "$label es accesible desde internet ($base$path)", 'Falta el archivo .htaccess en presencard/ o en presencard/back/ (son archivos ocultos): vuelve a extraer el ZIP con «Mostrar archivos ocultos».');
    } else {
        add($checks, 'Seguridad', 'ok', "$label está protegido (responde {$result[0]})");
    }
}

$result = fetchUrl($base . '/back/.env.local.php');

if ($result !== null && strpos($result[1], 'DATABASE_URL') !== false) {
    add($checks, 'Seguridad', 'fail', 'back/.env.local.php muestra su contenido en internet', 'Urgente: ese archivo debe devolver «Forbidden» o una página vacía. Revisa los .htaccess y cambia la contraseña de la base de datos.');
} elseif ($result !== null) {
    add($checks, 'Seguridad', 'ok', "La configuración (back/.env.local.php) no se muestra (responde {$result[0]})");
}

// ---------------------------------------------------------------- Resultado
$fails = count(array_filter($checks, static function (array $c): bool {
    return $c['status'] === 'fail';
}));
$warns = count(array_filter($checks, static function (array $c): bool {
    return $c['status'] === 'warn';
}));
$icons = ['ok' => '✔', 'warn' => '▲', 'fail' => '✖'];
$groups = [];

foreach ($checks as $check) {
    $groups[$check['group']][] = $check;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Diagnóstico · Presencard</title>
<style>
  body { margin: 0; padding: 24px 16px 48px; background: #f5f6fb; color: #1b1f3b; font: 16px/1.5 system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; }
  main { max-width: 760px; margin: 0 auto; }
  h1 { margin: 0 0 4px; font-size: 1.5rem; }
  h2 { margin: 28px 0 8px; font-size: .8125rem; letter-spacing: .05em; text-transform: uppercase; color: #252f80; }
  .summary { margin: 16px 0; padding: 14px 16px; border-radius: 12px; font-weight: 600; }
  .summary.ok { background: #e6f4eb; color: #006f2a; }
  .summary.warn { background: #fff4d6; color: #7a5a00; }
  .summary.fail { background: #fcecea; color: #b3261e; }
  ul { list-style: none; margin: 0; padding: 0; background: #fff; border: 1px solid #e1e4f0; border-radius: 12px; overflow: hidden; }
  li { display: flex; gap: 12px; padding: 10px 14px; border-top: 1px solid #e1e4f0; }
  li:first-child { border-top: 0; }
  .icon { flex: none; width: 1.4em; text-align: center; font-weight: 700; }
  .ok .icon { color: #008d36; } .warn .icon { color: #c78a00; } .fail .icon { color: #b3261e; }
  .hint { display: block; margin-top: 2px; color: #5b6180; font-size: .9rem; }
  .fail .hint { color: #b3261e; }
  footer { margin-top: 28px; padding: 14px 16px; border-radius: 12px; background: #fff; border: 1px solid #e1e4f0; }
</style>
</head>
<body>
<main>
  <h1>Diagnóstico de Presencard</h1>
  <p>Revisa que el servidor tenga todo lo necesario. No muestra contraseñas.</p>

  <?php if ($fails > 0) : ?>
    <div class="summary fail">✖ <?= $fails ?> problema(s) por corregir. Sigue la indicación de cada línea roja y recarga esta página.</div>
  <?php elseif ($warns > 0) : ?>
    <div class="summary warn">▲ Todo lo esencial funciona; hay <?= $warns ?> aviso(s) que conviene revisar.</div>
  <?php else : ?>
    <div class="summary ok">✔ Todo está listo.</div>
  <?php endif; ?>

  <?php foreach ($groups as $group => $items) : ?>
    <h2><?= e($group) ?></h2>
    <ul>
      <?php foreach ($items as $item) : ?>
        <li class="<?= e($item['status']) ?>">
          <span class="icon"><?= $icons[$item['status']] ?></span>
          <span><?= e($item['title']) ?><?php if ($item['hint'] !== '' && $item['status'] !== 'ok') : ?><span class="hint"><?= e($item['hint']) ?></span><?php endif; ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>

  <footer>
    <strong>Importante:</strong> cuando todo esté en verde, <strong>borra el archivo <code>diagnostico.php</code></strong> desde el Administrador de archivos
    (carpeta <code>public_html/presencard</code>). No debe quedarse en el servidor.
  </footer>
</main>
</body>
</html>
