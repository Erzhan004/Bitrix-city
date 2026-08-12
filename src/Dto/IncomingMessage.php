<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class IncomingMessage
{
  public function __construct(
    public string $messageId,
    public string $channelId,
    public string $chatType,
    public string $chatId,
    public string $text,
    public string $status,
    public string $type,
    public bool $isEcho,
    public ?string $contactPhone,
    public bool $isDeleted,
    public bool $isEdited,
  ) {
  }
}
