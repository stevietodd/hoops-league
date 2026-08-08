<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

$configFile = ROOT_PATH . '/config/config.php';
$localConfig = ROOT_PATH . '/config/config.local.php';
$config = require $configFile;
if (is_file($localConfig)) {
    $config = array_merge($config, require $localConfig);
}

date_default_timezone_set($config['timezone'] ?? 'America/New_York');

if (!empty($config['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

session_start();

require_once ROOT_PATH . '/app/helpers.php';
require_once ROOT_PATH . '/app/Database.php';
require_once ROOT_PATH . '/app/Auth.php';
require_once ROOT_PATH . '/app/Standings.php';
require_once ROOT_PATH . '/app/Router.php';

Database::init($config['db_path']);
Auth::boot();
