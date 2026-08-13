<?php

declare(strict_types=1);

namespace App\Bitrix;

use App\Config;
use App\Dto\Branch;
use Psr\Log\LoggerInterface;

final class DealService
{
  public function __construct(
    private readonly Config $config,
    private readonly BitrixClient $bitrix,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function findDealIdByLeadId(int $leadId): ?int
  {
    $entityTypeId = (int) $this->config->get('bitrix.entity_type.deal', 2);

    $result = $this->bitrix->call('crm.item.list', [
      'entityTypeId' => $entityTypeId,
      'filter' => [
        '=leadId' => $leadId,
      ],
      'select' => ['id', 'leadId', 'categoryId', 'stageId', 'title'],
      'order' => ['id' => 'DESC'],
    ]);

    $items = $result['items'] ?? [];
    if (!is_array($items) || $items === []) {
      return null;
    }

    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }

      $id = (int) ($item['id'] ?? 0);
      if ($id > 0) {
        return $id;
      }
    }

    return null;
  }

  public function createForBranch(
    Branch $branch,
    int $contactId,
    int $leadId,
    string $phone,
    string $city,
  ): int {
    $entityTypeId = (int) $this->config->get('bitrix.entity_type.deal', 2);
    $title = $this->buildTitle($branch, $phone, $city, $leadId);

    $fields = [
      'title' => $title,
      'categoryId' => $branch->categoryId,
      'stageId' => $branch->stageId,
      'contactId' => $contactId,
      'leadId' => $leadId,
      'opened' => 'Y',
    ];

    if ($branch->assignedById !== null) {
      $fields['assignedById'] = $branch->assignedById;
    }

    $result = $this->bitrix->call('crm.item.add', [
      'entityTypeId' => $entityTypeId,
      'fields' => $fields,
    ]);

    $item = $result['item'] ?? [];
    $dealId = (int) ($item['id'] ?? 0);

    if ($dealId <= 0) {
      throw new \RuntimeException('Deal create returned empty id.');
    }

    $this->logger->info('DEAL_CREATED', [
      'dealId' => $dealId,
      'leadId' => $leadId,
      'contactId' => $contactId,
      'branch' => $branch->key,
      'categoryId' => $branch->categoryId,
      'stageId' => $branch->stageId,
    ]);

    return $dealId;
  }

  private function buildTitle(Branch $branch, string $phone, string $city, int $leadId): string
  {
    $template = (string) $this->config->get(
      'deal.title_template',
      'Заявка WhatsApp — {branch} — +{phone}'
    );

    return strtr($template, [
      '{branch}' => $branch->name,
      '{phone}' => $phone,
      '{city}' => $city,
      '{lead_id}' => (string) $leadId,
    ]);
  }
}
