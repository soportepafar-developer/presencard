<?php
// Emula las reglas de .htaccess para el servidor integrado de PHP: archivos reales tal cual, el resto a index.php.
$root = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path !== '/' && is_file($root . $path)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/presencard/index.php';
$_SERVER['PHP_SELF'] = '/presencard/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/presencard/index.php';
require $root . '/presencard/index.php';
