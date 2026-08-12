<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

// Bypass Composer platform_check.php on PHP 8.2 shared hosting.
require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Config;
use App\WazzupWebhookHandler;

$config = new Config(dirname(__DIR__) . '/config.php');

if ($config->get('app.debug', false) !== true) {
  ini_set('display_errors', '0');
}

date_default_timezone_set((string) $config->get('app.timezone', 'UTC'));

WazzupWebhookHandler::createFromConfig($config)->handle();
