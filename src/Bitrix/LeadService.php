<?php

declare(strict_types=1);

namespace App\Bitrix;

use App\Config;
use App\Dto\LeadMatch;
use App\PhoneNormalizer;
use Psr\Log\LoggerInterface;

final class LeadService
{
  public function __construct(
    private readonly Config $config,
    private readonly BitrixClient $bitrix,
    private readonly PhoneNormalizer $phoneNormalizer,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function findUnprocessedLeadByPhone(string $normalizedPhone): LeadMatch
  {
    $leadIds = $this->findLeadIdsByPhone($normalizedPhone);

    if ($leadIds === []) {
      return LeadMatch::none();
    }

    $unprocessedStatus = (string) $this->config->require('lead.statuses.unprocessed');
    $entityTypeId = (int) $this->config->get('bitrix.entity_type.lead', 1);
    $matched = [];

    foreach ($leadIds as $leadId) {
      $lead = $this->getLead($leadId);
      if ($lead === null) {
        continue;
      }

      $stageId = (string) ($lead['stageId'] ?? $lead['STATUS_ID'] ?? '');
      if ($stageId !== $unprocessedStatus) {
        continue;
      }

      $matched[] = $lead;
    }

    if ($matched === []) {
      return LeadMatch::none();
    }

    if (count($matched) > 1) {
      $ids = array_map(static fn(array $lead): int => (int) ($lead['id'] ?? 0), $matched);

      return LeadMatch::multiple(array_values(array_filter($ids)));
    }

    $lead = $matched[0];
    $id = (int) ($lead['id'] ?? 0);

    return LeadMatch::single($id, $lead);
  }

  /** @return list<int> */
  public function findLeadIdsByPhone(string $normalizedPhone): array
  {
    $variants = $this->phoneNormalizer->lookupVariants($normalizedPhone);

    $result = $this->bitrix->call('crm.duplicate.findbycomm', [
      'entity_type' => 'LEAD',
      'type' => 'PHONE',
      'values' => $variants,
    ]);

    $leads = $result['LEAD'] ?? [];
    if (!is_array($leads)) {
      return [];
    }

    return array_values(array_unique(array_map('intval', $leads)));
  }

  /** @return array<string, mixed>|null */
  public function getLead(int $leadId): ?array
  {
    $entityTypeId = (int) $this->config->get('bitrix.entity_type.lead', 1);
    $cityField = (string) $this->config->require('lead.city_field');

    $result = $this->bitrix->call('crm.item.get', [
      'entityTypeId' => $entityTypeId,
      'id' => $leadId,
      'useOriginalUfNames' => 'Y',
    ]);

    $item = $result['item'] ?? null;
    if (!is_array($item)) {
      return null;
    }

    // Ensure city field key is accessible.
    if (!isset($item[$cityField]) && isset($item['ufCrm' . substr($cityField, 6)])) {
      // camelCase fallback ignored; useOriginalUfNames=Y should keep UF_CRM_*
    }

    return $item;
  }

  public function saveCity(int $leadId, string $city): void
  {
    $entityTypeId = (int) $this->config->get('bitrix.entity_type.lead', 1);
    $cityField = (string) $this->config->require('lead.city_field');

    $this->bitrix->call('crm.item.update', [
      'entityTypeId' => $entityTypeId,
      'id' => $leadId,
      'useOriginalUfNames' => 'Y',
      'fields' => [
        $cityField => $city,
      ],
    ]);

    $this->logger->info('CITY_SAVED_TO_LEAD', [
      'leadId' => $leadId,
      'city' => $city,
    ]);
  }

  public function markProcessed(int $leadId): void
  {
    $entityTypeId = (int) $this->config->get('bitrix.entity_type.lead', 1);
    $processedStatus = (string) $this->config->require('lead.statuses.processed');

    $this->bitrix->call('crm.item.update', [
      'entityTypeId' => $entityTypeId,
      'id' => $leadId,
      'fields' => [
        'stageId' => $processedStatus,
      ],
    ]);

    $this->logger->info('LEAD_MARKED_PROCESSED', [
      'leadId' => $leadId,
      'status' => $processedStatus,
    ]);
  }

  public function isProcessed(array $lead): bool
  {
    $processedStatus = (string) $this->config->require('lead.statuses.processed');
    $stageId = (string) ($lead['stageId'] ?? '');

    return $stageId === $processedStatus;
  }

  public function getCreatedTimestamp(array $lead): ?int
  {
    $created = $lead['createdTime'] ?? $lead['DATE_CREATE'] ?? null;
    if (!is_string($created) || $created === '') {
      return null;
    }

    $ts = strtotime($created);

    return $ts === false ? null : $ts;
  }
}
