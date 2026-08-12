<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
  public static function send(int $statusCode, array $payload): void
  {
    if (!headers_sent()) {
      http_response_code($statusCode);
      header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }
}
