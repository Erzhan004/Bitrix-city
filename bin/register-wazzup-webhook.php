#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config;

$config = new Config(dirname(__DIR__) . '/config.php');

$apiKey = (string) $config->require('wazzup.api_key');
$secret = (string) $config->require('wazzup.webhook_secret');
$baseUrl = rtrim((string) $config->require('wazzup.public_webhook_url'), '?');
$webhooksUri = $baseUrl . '?key=' . rawurlencode($secret);

$payload = json_encode([
  'webhooksUri' => $webhooksUri,
  'subscriptions' => [
    'messagesAndStatuses' => true,
  ],
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

$ch = curl_init('https://api.wazzup24.com/v3/webhooks');
curl_setopt_array($ch, [
  CURLOPT_CUSTOMREQUEST => 'PATCH',
  CURLOPT_POSTFIELDS => $payload,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
    'Accept: application/json',
  ],
]);

$response = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
  fwrite(STDERR, "Transport error: {$error}\n");
  exit(1);
}

fwrite(STDOUT, "HTTP {$code}\n{$response}\n");

if ($code >= 200 && $code < 300) {
  fwrite(STDOUT, "\nWebhook registered:\n{$webhooksUri}\n");
  exit(0);
}

exit(1);
