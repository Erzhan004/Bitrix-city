<?php

declare(strict_types=1);

namespace App\Bitrix;

use App\Config;
use App\Dto\DealMatch;
use App\PhoneNormalizer;
use Psr\Log\LoggerInterface;

final class DealService
{
  public function __construct(
    private readonly Config $config,
    private readonly BitrixClient $bitrix,
    private readonly PhoneNormalizer $phoneNormalizer,
    private readonly LoggerInterface $logger,
  ) {
  }

  /** @return list<int> */
  public function findContactIdsByPhone(string $normalizedPhone): array
  {
    $variants = $this->phoneNormalizer->lookupVariants($normalizedPhone);
    $method = (string) $this->config->get('bitrix.contact_lookup.method', 'crm.duplicate.findbycomm');

    $result = $this->bitrix->call($method, [
      'entity_type' => (string) $this->config->get('bitrix.contact_lookup.entity_type', 'CONTACT'),
      'type' => (string) $this->config->get('bitrix.contact_lookup.type', 'PHONE'),
      'values' => $variants,
    ]);

    $contacts = $result['CONTACT'] ?? [];

    if (!is_array($contacts)) {
      return [];
    }

    return array_values(array_unique(array_map('intval', $contacts)));
  }

  /**
   * Find exactly one active deal waiting for a configured flow.
   *
   * @param array<string, array<string, mixed>> $flows
   */
  public function findWaitingDeal(int $contactId, array $flows): DealMatch
  {
    $entityTypeId = (int) $this->config->get('bitrix.deal_entity_type_id', 2);
    $globalCategoryId = $this->config->get('bitrix.category_id');

    foreach ($flows as $flowName => $flow) {
      $waitingMode = (string) ($flow['waiting_mode'] ?? 'field');
      $categoryId = $flow['category_id'] ?? $globalCategoryId;

      $filter = [
        '=contactId' => $contactId,
      ];

      $select = ['id', 'stageId', 'categoryId', 'contactId'];

      if ($waitingMode === 'stage') {
        $filter['=stageId'] = (string) $flow['waiting_stage'];
      } else {
        $waitingField = (string) $flow['waiting_field'];
        $filter['=' . $waitingField] = (string) $flow['waiting_yes'];
        $select[] = $waitingField;
      }

      if ($categoryId !== null && $categoryId !== '') {
        $filter['=categoryId'] = (int) $categoryId;
      }

      $result = $this->bitrix->call('crm.item.list', [
        'entityTypeId' => $entityTypeId,
        'useOriginalUfNames' => 'Y',
        'filter' => $filter,
        'select' => $select,
        'order' => ['id' => 'DESC'],
      ]);

      $items = $result['items'] ?? $result;

      if (!is_array($items)) {
        continue;
      }

      $activeDeals = $this->filterActiveDeals($items);

      if ($activeDeals === []) {
        continue;
      }

      $dealIds = array_map(static fn(array $deal): int => (int) $deal['id'], $activeDeals);

      if (count($dealIds) === 1) {
        return DealMatch::single($dealIds[0], (string) $flowName);
      }

      if (count($dealIds) > 1) {
        return DealMatch::multiple($dealIds, (string) $flowName);
      }
    }

    return DealMatch::none();
  }

  /**
   * @param array<string, mixed> $flow
   * @param array<string, mixed> $fields
   */
  public function updateDeal(int $dealId, array $flow, array $fields): void
  {
    $entityTypeId = (int) $this->config->get('bitrix.deal_entity_type_id', 2);

    $payload = array_merge($fields, [
      'stageId' => (string) $flow['after_stage'],
    ]);

    $waitingMode = (string) ($flow['waiting_mode'] ?? 'field');
    if ($waitingMode === 'field') {
      $payload[(string) $flow['waiting_field']] = (string) $flow['waiting_no'];
    }

    $this->bitrix->call('crm.item.update', [
      'entityTypeId' => $entityTypeId,
      'id' => $dealId,
      'useOriginalUfNames' => 'Y',
      'fields' => $payload,
    ]);
  }

  /** @param list<array<string, mixed>> $items */
  private function filterActiveDeals(array $items): array
  {
    $semanticsFilter = $this->config->get('bitrix.active_deal_semantics', ['process']);

    if (!is_array($semanticsFilter) || $semanticsFilter === []) {
      return $items;
    }

    $stageSemantics = $this->loadStageSemanticsMap();
    $active = [];

    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }

      $stageId = (string) ($item['stageId'] ?? '');
      $semantics = $stageSemantics[$stageId] ?? 'process';

      if (in_array($semantics, $semanticsFilter, true)) {
        $active[] = $item;
      }
    }

    return $active;
  }

  /** @return array<string, string> stageId => semantics */
  private function loadStageSemanticsMap(): array
  {
    static $cache = null;

    if (is_array($cache)) {
      return $cache;
    }

    $cache = [];

    $entityIds = ['DEAL_STAGE'];

    try {
      $categoriesResult = $this->bitrix->call('crm.category.list', [
        'entityTypeId' => (int) $this->config->get('bitrix.deal_entity_type_id', 2),
      ]);

      $categories = $categoriesResult['categories'] ?? $categoriesResult;

      if (is_array($categories)) {
        foreach ($categories as $category) {
          if (is_array($category) && isset($category['id'])) {
            $entityIds[] = 'DEAL_STAGE_' . $category['id'];
          }
        }
      }
    } catch (\Throwable $e) {
      $this->logger->warning('Cannot load deal categories for stage semantics', [
        'message' => $e->getMessage(),
      ]);
    }

    foreach (array_unique($entityIds) as $entityId) {
      try {
        $statuses = $this->bitrix->call('crm.status.list', [
          'filter' => ['ENTITY_ID' => $entityId],
          'select' => ['STATUS_ID', 'EXTRA'],
        ]);
      } catch (\Throwable $e) {
        $this->logger->warning('Cannot load deal stage semantics', [
          'entity_id' => $entityId,
          'message' => $e->getMessage(),
        ]);
        continue;
      }

      $rows = is_array($statuses) ? $statuses : [];

      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }

        $statusId = (string) ($row['STATUS_ID'] ?? '');
        if ($statusId === '') {
          continue;
        }

        $extra = $row['EXTRA'] ?? [];
        $semantics = 'process';

        if (is_array($extra) && isset($extra['SEMANTICS'])) {
          $semantics = (string) $extra['SEMANTICS'];
        }

        $cache[$statusId] = $semantics;
      }
    }

    return $cache;
  }
}
