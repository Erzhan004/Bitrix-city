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
    $this->validate();
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
  public function branches(): array
  {
    $branches = $this->get('branches', []);

    return is_array($branches) ? $branches : [];
  }

  private function validate(): void
  {
    $this->require('bitrix.webhook');
    $this->require('wazzup.webhook_secret');
    $this->require('lead.city_field');
    $this->require('lead.statuses.unprocessed');
    $this->require('lead.statuses.processed');

    foreach ($this->branches() as $key => $branch) {
      if (!is_array($branch)) {
        throw new RuntimeException('Invalid branch config: ' . $key);
      }

      foreach (['name', 'cities', 'category_id', 'stage_id'] as $field) {
        if (!array_key_exists($field, $branch) || $branch[$field] === '' || $branch[$field] === null) {
          throw new RuntimeException(sprintf('Branch "%s" is missing "%s".', $key, $field));
        }
      }

      if (!is_array($branch['cities']) || $branch['cities'] === []) {
        throw new RuntimeException(sprintf('Branch "%s" must have non-empty cities list.', $key));
      }

      $stageId = (string) $branch['stage_id'];
      $categoryId = (int) $branch['category_id'];

      if ($categoryId > 0 && !str_starts_with($stageId, 'C' . $categoryId . ':')) {
        // Soft warning only in logs later; still valid for custom stage codes.
      }
    }
  }
}
