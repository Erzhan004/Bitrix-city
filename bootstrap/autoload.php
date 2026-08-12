<?php

declare(strict_types=1);

/**
 * Load Composer autoloader without platform_check.php (PHP 8.2 on shared hosting).
 */
$vendorDir = dirname(__DIR__) . '/vendor';

if (!is_file($vendorDir . '/composer/autoload_static.php')) {
  throw new RuntimeException('Run composer install on the server first.');
}

require $vendorDir . '/composer/ClassLoader.php';

$loader = new Composer\Autoload\ClassLoader($vendorDir);

$staticFile = $vendorDir . '/composer/autoload_static.php';
require $staticFile;

if (!preg_match('/class (ComposerStaticInit[a-f0-9]+)/', (string) file_get_contents($staticFile), $matches)) {
  throw new RuntimeException('Cannot detect Composer static autoload class.');
}

/** @var class-string $staticClass */
$staticClass = 'Composer\\Autoload\\' . $matches[1];

call_user_func($staticClass::getInitializer($loader));

$loader->register(true);

foreach ($staticClass::$files as $fileIdentifier => $file) {
  if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
    $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
    require $file;
  }
}

return $loader;
