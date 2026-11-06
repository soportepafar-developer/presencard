<?php

/*
 * Punto de entrada de Presencard en producción (cPanel).
 *
 * Todo el proyecto vive en una sola carpeta:
 *
 *   public_html/presencard/index.php   <- este archivo
 *   public_html/presencard/app/        <- pantallas de la aplicación (Angular)
 *   public_html/presencard/back/       <- el programa (Symfony). Está protegido: no se puede abrir desde internet.
 */

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

// Producción: los errores no se muestran en pantalla (se registran en el log del servidor) y se silencian los
// avisos de obsolescencia de versiones de PHP más nuevas que Symfony 5.2, que no afectan al funcionamiento.
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);

$appDir = __DIR__ . '/back';

require $appDir . '/vendor/autoload.php';

// La configuración real (con las contraseñas) está en back/.env.local.php, un archivo PHP que no muestra nada si se abre.
(new Dotenv())->bootEnv($appDir . '/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    Debug::enable();
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
