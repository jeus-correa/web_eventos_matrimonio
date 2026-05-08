<?php
/**
 * Arranque común: sesión, zona horaria, config, autoload Composer, DB.
 */
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';

$settingsFile = dirname(__DIR__) . '/config/settings.php';
if (!is_file($settingsFile)) {
    http_response_code(503);
    echo 'Falta config/settings.php. Copia config/settings.sample.php a config/settings.php y configura.';
    exit;
}
require_once $settingsFile;

date_default_timezone_set(APP_TIMEZONE);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/functions.php';
