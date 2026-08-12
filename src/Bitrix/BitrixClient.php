<?php

declare(strict_types=1);

namespace App\Bitrix;

use App\Config;
use App\Exception\BitrixApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

final class BitrixClient
{
  private Client $http;
  private string $webhookBase;

  public function __construct(
    private readonly Config $config,
    private readonly LoggerInterface $logger,
  ) {
    $this->webhookBase = rtrim((string) $config->require('bitrix.webhook'), '/') . '/';

    $this->http = new Client([
      'base_uri' => $this->webhookBase,
      'connect_timeout' => (float) $config->get('bitrix.http.connect_timeout', 5.0),
      'timeout' => (float) $config->get('bitrix.http.timeout', 20.0),
      'http_errors' => false,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);
  }

  /**
   * @param array<string, mixed> $params
   * @return array<string, mixed>
   */
  public function call(string $method, array $params = []): array
  {
    $attempts = max(1, (int) $this->config->get('bitrix.http.retry_attempts', 3));
    $delayMs = max(0, (int) $this->config->get('bitrix.http.retry_delay_ms', 500));
    $lastException = null;

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
      try {
        return $this->executeCall($method, $params);
      } catch (BitrixApiException $e) {
        $lastException = $e;

        if (!$this->isRetryable($e) || $attempt >= $attempts) {
          throw $e;
        }

        $this->logger->warning('Bitrix API retry', [
          'method' => $method,
          'attempt' => $attempt,
          'error' => $e->bitrixError,
        ]);

        if ($delayMs > 0) {
          usleep($delayMs * 1000 * $attempt);
        }
      }
    }

    throw $lastException ?? new BitrixApiException('Bitrix API call failed.');
  }

  /**
   * @param array<string, mixed> $params
   * @return array<string, mixed>
   */
  private function executeCall(string $method, array $params): array
  {
    try {
      $response = $this->http->post($method, [
        'json' => $params,
      ]);
    } catch (GuzzleException $e) {
      $this->logger->error('Bitrix API transport error', [
        'method' => $method,
        'message' => $e->getMessage(),
      ]);

      throw new BitrixApiException('Bitrix transport error: ' . $e->getMessage(), previous: $e);
    }

    $status = $response->getStatusCode();
    $body = (string) $response->getBody();

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
      throw new BitrixApiException('Invalid JSON from Bitrix24.', httpStatus: $status);
    }

    if (isset($decoded['error'])) {
      $this->logger->error('Bitrix API error', [
        'method' => $method,
        'error' => $decoded['error'],
        'error_description' => $decoded['error_description'] ?? null,
      ]);

      throw new BitrixApiException(
        'Bitrix API error: ' . (string) $decoded['error'],
        bitrixError: (string) $decoded['error'],
        bitrixDescription: isset($decoded['error_description']) ? (string) $decoded['error_description'] : null,
        httpStatus: $status,
      );
    }

    $result = $decoded['result'] ?? $decoded;

    return is_array($result) ? $result : ['value' => $result];
  }

  private function isRetryable(BitrixApiException $e): bool
  {
    if ($e->httpStatus >= 500) {
      return true;
    }

    return in_array($e->bitrixError, [
      'QUERY_LIMIT_EXCEEDED',
      'OPERATION_TIME_LIMIT',
      'OVERLOAD_LIMIT',
      'INTERNAL_SERVER_ERROR',
      'ERROR_UNEXPECTED_ANSWER',
    ], true);
  }
}
