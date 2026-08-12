<?php

declare(strict_types=1);

namespace App\Contract;

interface ValueProcessorInterface
{
  /**
   * @param array<string, mixed> $options Flow processor_options from config.
   */
  public function process(string $rawText, array $options = []): ?string;
}
