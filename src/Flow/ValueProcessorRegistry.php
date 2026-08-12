<?php

declare(strict_types=1);

namespace App\Flow;

use App\CityProcessor;
use App\Contract\ValueProcessorInterface;
use RuntimeException;

final class ValueProcessorRegistry
{
  /** @var array<string, ValueProcessorInterface> */
  private array $processors;

  public function __construct()
  {
    $text = new CityProcessor();

    $this->processors = [
      'text' => $text,
      'city' => $text,
    ];
  }

  public function get(string $name): ValueProcessorInterface
  {
    if (!isset($this->processors[$name])) {
      throw new RuntimeException('Unknown value processor: ' . $name);
    }

    return $this->processors[$name];
  }
}
