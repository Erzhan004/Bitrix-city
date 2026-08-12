<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class BitrixApiException extends RuntimeException
{
  public function __construct(
    string $message,
    public readonly ?string $bitrixError = null,
    public readonly ?string $bitrixDescription = null,
    public readonly int $httpStatus = 0,
    ?\Throwable $previous = null,
  ) {
    parent::__construct($message, 0, $previous);
  }
}
