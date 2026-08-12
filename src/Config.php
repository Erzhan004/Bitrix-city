<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class Config
{
  /** @var array<string, mixed> */
  private array $data;

  public function __construct(?string $path = null)
  {
    $path ??= dirname(__DIR__) . '/config.php';

    if (!is_readable($path)) {
      throw new RuntimeException('Configuration file is not readable: ' . $path);
    }

    $loaded = require $path;

    if (!is_array($loaded)) {
      throw new RuntimeException('Configuration file must return an array.');
    }

    $this->data = $loaded;
  }

  public function get(string $key, mixed $default = null): mixed
  {
    $segments = explode('.', $key);
    $value = $this->data;

    foreach ($segments as $segment) {
      if (!is_array($value) || !array_key_exists($segment, $value)) {
        return $default;
      }
      $value = $value[$segment];
    }

    return $value;
  }

  public function require(string $key): mixed
  {
    $value = $this->get($key);

    if ($value === null || $value === '') {
      throw new RuntimeException('Missing required configuration value: ' . $key);
    }

    return $value;
  }

  /** @return array<string, mixed> */
  public function all(): array
  {
    return $this->data;
  }

  /** @return array<string, array<string, mixed>> */
  public function enabledFlows(): array
  {
    $flows = $this->get('flows', []);

    if (!is_array($flows)) {
      return [];
    }

    $enabled = [];

    foreach ($flows as $name => $flow) {
      if (!is_array($flow)) {
        continue;
      }

      if (($flow['enabled'] ?? true) !== true) {
        continue;
      }

      $this->validateFlow((string) $name, $flow);
      $enabled[(string) $name] = $flow;
    }

    return $enabled;
  }

  /** @param array<string, mixed> $flow */
  private function validateFlow(string $name, array $flow): void
  {
    $mode = (string) ($flow['waiting_mode'] ?? 'field');

    if (!in_array($mode, ['field', 'stage'], true)) {
      throw new RuntimeException(sprintf('Flow "%s" has invalid waiting_mode "%s".', $name, $mode));
    }

    if (!isset($flow['target_field']) || $flow['target_field'] === '') {
      throw new RuntimeException(sprintf('Flow "%s" is missing "target_field".', $name));
    }

    if (!isset($flow['after_stage']) || $flow['after_stage'] === '') {
      throw new RuntimeException(sprintf('Flow "%s" is missing "after_stage".', $name));
    }

    if ($mode === 'stage') {
      if (!isset($flow['waiting_stage']) || $flow['waiting_stage'] === '') {
        throw new RuntimeException(sprintf('Flow "%s" is missing "waiting_stage".', $name));
      }

      return;
    }

    foreach (['waiting_field', 'waiting_yes', 'waiting_no'] as $field) {
      if (!isset($flow[$field]) || $flow[$field] === '') {
        throw new RuntimeException(sprintf('Flow "%s" is missing "%s".', $name, $field));
      }
    }
  }
}
