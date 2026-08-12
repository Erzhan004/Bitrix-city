<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

// Hoster.kz may run PHP 8.2 while composer.lock was built with 8.3 platform check.
putenv('COMPOSER_DISABLE_PLATFORM_CHECK=1');

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config;
use App\WazzupWebhookHandler;

$config = new Config(dirname(__DIR__) . '/config.php');

if ($config->get('app.debug', false) !== true) {
  ini_set('display_errors', '0');
}

date_default_timezone_set((string) $config->get('app.timezone', 'UTC'));

WazzupWebhookHandler::createFromConfig($config)->handle();
