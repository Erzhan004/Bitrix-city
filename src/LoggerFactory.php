<?php

declare(strict_types=1);

namespace App;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
  public static function create(Config $config): LoggerInterface
  {
    $logger = new Logger('wazzup-bitrix');

    if ($config->get('logging.enabled', true) !== true) {
      return new Logger('null');
    }

    $level = self::mapLevel((string) $config->get('logging.level', 'info'));
    $file = (string) $config->require('logging.file');
    $dir = dirname($file);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
      throw new \RuntimeException('Cannot create log directory: ' . $dir);
    }

    $handler = new RotatingFileHandler($file, 14, $level, true, 0664);
    $handler->setFormatter(new LineFormatter(
      "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
      'Y-m-d H:i:s',
      true,
      true
    ));

    $logger->pushHandler($handler);
    $logger->pushProcessor(new PsrLogMessageProcessor());

    return $logger;
  }

  private static function mapLevel(string $level): Level
  {
    return match (strtolower($level)) {
      'debug' => Level::Debug,
      'notice' => Level::Notice,
      'warning', 'warn' => Level::Warning,
      'error' => Level::Error,
      'critical' => Level::Critical,
      default => Level::Info,
    };
  }
}
