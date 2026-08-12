<?php

declare(strict_types=1);

namespace App\Http;

use App\Config;

final class WebhookSecurity
{
  public function __construct(private readonly Config $config)
  {
  }

  public function validateRequestMethod(): ?string
  {
    $allowed = $this->config->get('security.allowed_methods', ['POST']);

    if (!is_array($allowed)) {
      $allowed = ['POST'];
    }

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if (!in_array($method, $allowed, true)) {
      return 'Method not allowed';
    }

    return null;
  }

  public function validateAccessKey(): ?string
  {
    $expected = (string) $this->config->require('wazzup.webhook_secret');
    $provided = (string) ($_GET['key'] ?? '');

    if ($provided === '' || !hash_equals($expected, $provided)) {
      return 'Invalid access key';
    }

    return null;
  }

  public function validateContentType(): ?string
  {
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
    $contentType = trim(explode(';', $contentType)[0]);

    $allowed = $this->config->get('security.content_types', ['application/json']);

    if (!is_array($allowed)) {
      return null;
    }

    $allowed = array_map(static fn(string $type): string => strtolower(trim(explode(';', $type)[0])), $allowed);

    if ($contentType !== '' && !in_array($contentType, $allowed, true)) {
      return 'Unsupported content type';
    }

    return null;
  }

  public function readBody(): string
  {
    $maxBytes = (int) $this->config->get('security.max_body_bytes', 1048576);
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);

    if ($raw === false) {
      return '';
    }

    if (strlen($raw) > $maxBytes) {
      throw new \RuntimeException('Request body too large');
    }

    return $raw;
  }
}
