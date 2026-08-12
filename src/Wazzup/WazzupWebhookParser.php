<?php

declare(strict_types=1);

namespace App\Wazzup;

use App\Config;
use App\Dto\IncomingMessage;
use App\PhoneNormalizer;

/**
 * Parses Wazzup API v3 webhook payloads (messages / statuses).
 * @see https://wazzup24.com/help/api-en/webhooks/
 */
final class WazzupWebhookParser
{
  public function __construct(
    private readonly Config $config,
    private readonly PhoneNormalizer $phoneNormalizer,
  ) {
  }

  public function isTestRequest(array $payload): bool
  {
    return ($payload['test'] ?? false) === true;
  }

  /**
   * @return list<IncomingMessage>
   */
  public function extractIncomingTextMessages(array $payload): array
  {
    if (!isset($payload['messages']) || !is_array($payload['messages'])) {
      return [];
    }

    $allowedChannels = $this->config->get('wazzup.allowed_channel_ids');
    $maxLength = (int) $this->config->get('logging.max_message_length', 255);
    $messages = [];

    foreach ($payload['messages'] as $item) {
      if (!is_array($item)) {
        continue;
      }

      if (!$this->isProcessableMessage($item)) {
        continue;
      }

      $channelId = (string) ($item['channelId'] ?? '');
      if (is_array($allowedChannels) && $allowedChannels !== [] && !in_array($channelId, $allowedChannels, true)) {
        continue;
      }

      $text = trim((string) ($item['text'] ?? ''));
      if ($text === '') {
        continue;
      }

      if (mb_strlen($text) > $maxLength) {
        continue;
      }

      $phone = $this->resolvePhone($item);
      if ($phone === null) {
        continue;
      }

      $messages[] = new IncomingMessage(
        messageId: (string) ($item['messageId'] ?? ''),
        channelId: $channelId,
        chatType: (string) ($item['chatType'] ?? ''),
        chatId: (string) ($item['chatId'] ?? ''),
        text: $text,
        status: (string) ($item['status'] ?? ''),
        type: (string) ($item['type'] ?? ''),
        isEcho: (bool) ($item['isEcho'] ?? true),
        contactPhone: $phone,
        isDeleted: (bool) ($item['isDeleted'] ?? false),
        isEdited: (bool) ($item['isEdited'] ?? false),
      );
    }

    return $messages;
  }

  /** @param array<string, mixed> $item */
  private function isProcessableMessage(array $item): bool
  {
    if (($item['messageId'] ?? '') === '') {
      return false;
    }

    if (($item['status'] ?? '') !== 'inbound') {
      return false;
    }

    if (($item['type'] ?? '') !== 'text') {
      return false;
    }

    if (($item['isEcho'] ?? true) !== false) {
      return false;
    }

    if (($item['isDeleted'] ?? false) === true) {
      return false;
    }

    return true;
  }

  /** @param array<string, mixed> $item */
  private function resolvePhone(array $item): ?string
  {
    $chatType = (string) ($item['chatType'] ?? '');
    $chatId = (string) ($item['chatId'] ?? '');

    $candidates = [];

    if (in_array($chatType, ['whatsapp', 'viber', 'whatsgroup'], true)) {
      $candidates[] = $chatId;
    }

    $contact = $item['contact'] ?? null;
    if (is_array($contact) && isset($contact['phone'])) {
      $candidates[] = (string) $contact['phone'];
    }

    if ($chatId !== '' && !in_array($chatType, ['instagram'], true)) {
      $candidates[] = $chatId;
    }

    foreach ($candidates as $candidate) {
      $normalized = $this->phoneNormalizer->normalize($candidate);
      if ($normalized !== null) {
        return $normalized;
      }
    }

    return null;
  }
}
