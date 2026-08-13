<?php

declare(strict_types=1);

namespace App\Flow;

use App\Bitrix\ContactService;
use App\Bitrix\DealService;
use App\Bitrix\LeadService;
use App\Branch\BranchResolver;
use App\Dto\IncomingMessage;
use Psr\Log\LoggerInterface;

/**
 * While lead is "Не обработан": ignore any message that is not a known branch city.
 * Only a matched city creates a deal and marks the lead processed.
 * No SQLite / no "Ожидаем город" field needed.
 */
final class FlowOrchestrator
{
  public function __construct(
    private readonly LeadService $leadService,
    private readonly ContactService $contactService,
    private readonly DealService $dealService,
    private readonly BranchResolver $branchResolver,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function processMessage(IncomingMessage $message): string
  {
    $this->logger->info('INBOUND_MESSAGE_RECEIVED', [
      'messageId' => $message->messageId,
      'phone' => $message->contactPhone,
      'type' => $message->type,
      'chatType' => $message->chatType,
      'text' => $message->text,
    ]);

    $phone = (string) $message->contactPhone;
    $this->logger->info('PHONE_NORMALIZED', ['phone' => $phone]);

    $match = $this->leadService->findUnprocessedLeadByPhone($phone);

    if ($match->status === 'none') {
      if ($this->hasProcessedLead($phone)) {
        $this->logger->info('LEAD_ALREADY_PROCESSED', [
          'phone' => $phone,
          'messageId' => $message->messageId,
        ]);

        return 'lead_already_processed';
      }

      $this->logger->info('UNPROCESSED_LEAD_NOT_FOUND', [
        'phone' => $phone,
        'messageId' => $message->messageId,
      ]);

      return 'unprocessed_lead_not_found';
    }

    if ($match->status === 'multiple') {
      $this->logger->error('MULTIPLE_UNPROCESSED_LEADS', [
        'phone' => $phone,
        'lead_ids' => $match->leadIds,
        'messageId' => $message->messageId,
      ]);

      return 'multiple_unprocessed_leads';
    }

    $leadId = (int) $match->leadId;
    $lead = $match->lead ?? [];

    $this->logger->info('UNPROCESSED_LEAD_FOUND', [
      'leadId' => $leadId,
      'phone' => $phone,
      'messageId' => $message->messageId,
    ]);

    $cityRaw = trim($message->text);
    $branch = $this->branchResolver->resolve($cityRaw);

    // "Здравствуйте", "Караганда", etc. — keep waiting, do not save.
    if ($branch === null) {
      $this->logger->info('WAITING_FOR_CITY_IGNORED', [
        'leadId' => $leadId,
        'messageId' => $message->messageId,
        'text' => $cityRaw,
      ]);

      return 'waiting_for_city';
    }

    $warning = $this->branchResolver->validateBranchStage($branch);
    if ($warning !== null) {
      $this->logger->warning('BRANCH_STAGE_CATEGORY_MISMATCH', [
        'branch' => $branch->key,
        'warning' => $warning,
      ]);
    }

    $this->logger->info('BRANCH_RESOLVED_' . strtoupper($branch->key), [
      'leadId' => $leadId,
      'branch' => $branch->key,
      'city' => $cityRaw,
      'categoryId' => $branch->categoryId,
      'stageId' => $branch->stageId,
    ]);

    $this->leadService->saveCity($leadId, $cityRaw);

    $existingDealId = $this->dealService->findDealIdByLeadId($leadId);
    if ($existingDealId !== null) {
      $this->logger->info('DEAL_ALREADY_EXISTS', [
        'dealId' => $existingDealId,
        'leadId' => $leadId,
      ]);

      $this->leadService->markProcessed($leadId);

      return 'deal_created';
    }

    $contactId = $this->contactService->findOrCreateFromLead($phone, $lead);

    $this->dealService->createForBranch(
      $branch,
      $contactId,
      $leadId,
      $phone,
      $cityRaw
    );

    // Only after successful deal creation:
    $this->leadService->markProcessed($leadId);

    return 'deal_created';
  }

  private function hasProcessedLead(string $phone): bool
  {
    $leadIds = $this->leadService->findLeadIdsByPhone($phone);

    foreach ($leadIds as $leadId) {
      $lead = $this->leadService->getLead($leadId);
      if (is_array($lead) && $this->leadService->isProcessed($lead)) {
        return true;
      }
    }

    return false;
  }
}
