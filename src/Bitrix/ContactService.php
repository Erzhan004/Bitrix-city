<?php

declare(strict_types=1);

namespace App\Bitrix;

use App\Config;
use App\PhoneNormalizer;
use Psr\Log\LoggerInterface;

final class ContactService
{
  public function __construct(
    private readonly Config $config,
    private readonly BitrixClient $bitrix,
    private readonly PhoneNormalizer $phoneNormalizer,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function findOrCreateFromLead(string $normalizedPhone, array $lead): int
  {
    $existing = $this->findContactIdsByPhone($normalizedPhone);

    if ($existing !== []) {
      $contactId = $existing[0];
      $this->logger->info('CONTACT_FOUND', [
        'contactId' => $contactId,
        'phone' => $normalizedPhone,
      ]);

      return $contactId;
    }

    $contactId = $this->createFromLead($normalizedPhone, $lead);

    $this->logger->info('CONTACT_CREATED', [
      'contactId' => $contactId,
      'phone' => $normalizedPhone,
    ]);

    return $contactId;
  }

  /** @return list<int> */
  public function findContactIdsByPhone(string $normalizedPhone): array
  {
    $variants = $this->phoneNormalizer->lookupVariants($normalizedPhone);

    $result = $this->bitrix->call('crm.duplicate.findbycomm', [
      'entity_type' => 'CONTACT',
      'type' => 'PHONE',
      'values' => $variants,
    ]);

    $contacts = $result['CONTACT'] ?? [];
    if (!is_array($contacts)) {
      return [];
    }

    return array_values(array_unique(array_map('intval', $contacts)));
  }

  /** @param array<string, mixed> $lead */
  private function createFromLead(string $normalizedPhone, array $lead): int
  {
    $entityTypeId = (int) $this->config->get('bitrix.entity_type.contact', 3);

    $fields = [
      'name' => (string) ($lead['name'] ?? $lead['NAME'] ?? ''),
      'lastName' => (string) ($lead['lastName'] ?? $lead['LAST_NAME'] ?? ''),
      'secondName' => (string) ($lead['secondName'] ?? $lead['SECOND_NAME'] ?? ''),
      'opened' => 'Y',
      'fm' => [
        [
          'typeId' => 'PHONE',
          'valueType' => 'MOBILE',
          'value' => '+' . $normalizedPhone,
        ],
      ],
    ];

    if ($fields['name'] === '' && $fields['lastName'] === '') {
      $fields['name'] = (string) ($lead['title'] ?? $lead['TITLE'] ?? ('WhatsApp ' . $normalizedPhone));
    }

    $email = $this->extractEmailFromLead($lead);
    if ($email !== null) {
      $fields['fm'][] = [
        'typeId' => 'EMAIL',
        'valueType' => 'WORK',
        'value' => $email,
      ];
    }

    if (!empty($lead['assignedById'])) {
      $fields['assignedById'] = (int) $lead['assignedById'];
    }

    $result = $this->bitrix->call('crm.item.add', [
      'entityTypeId' => $entityTypeId,
      'fields' => $fields,
    ]);

    $item = $result['item'] ?? [];
    $id = (int) ($item['id'] ?? 0);

    if ($id <= 0) {
      throw new \RuntimeException('Contact create returned empty id.');
    }

    return $id;
  }

  /** @param array<string, mixed> $lead */
  private function extractEmailFromLead(array $lead): ?string
  {
    // crm.item.get may not include fm by default; try known keys.
    foreach (['email', 'EMAIL'] as $key) {
      if (!empty($lead[$key]) && is_string($lead[$key])) {
        return $lead[$key];
      }
    }

    return null;
  }
}
