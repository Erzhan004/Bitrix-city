<?php

declare(strict_types=1);

namespace App\Flow;

use App\Bitrix\DealService;
use App\Config;
use App\Dto\IncomingMessage;
use Psr\Log\LoggerInterface;

final class FlowOrchestrator
{
  public function __construct(
    private readonly Config $config,
    private readonly DealService $dealService,
    private readonly ValueProcessorRegistry $processors,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function processMessage(IncomingMessage $message): string
  {
    $this->logger->info('Processing incoming message', [
      'messageId' => $message->messageId,
      'phone' => $message->contactPhone,
      'message_type' => $message->type,
      'chatType' => $message->chatType,
    ]);

    $contactIds = $this->dealService->findContactIdsByPhone((string) $message->contactPhone);

    if ($contactIds === []) {
      $this->logger->info('Contact not found', ['phone' => $message->contactPhone]);

      return 'contact_not_found';
    }

    $this->logger->info('Contact found', ['contact_ids' => $contactIds]);

    $flows = $this->config->enabledFlows();

    foreach ($contactIds as $contactId) {
      $match = $this->dealService->findWaitingDeal($contactId, $flows);

      if ($match->status === 'none') {
        $this->logger->info('Ignored because deal is not in waiting stage/field', [
          'contact_id' => $contactId,
        ]);
        continue;
      }

      if ($match->status === 'multiple') {
        $this->logger->error('MULTIPLE_WAITING_DEALS', [
          'contact_id' => $contactId,
          'flow' => $match->flowName,
          'deal_ids' => $match->dealIds,
        ]);

        return 'multiple_waiting_deals';
      }

      $flowName = (string) $match->flowName;
      $flow = $flows[$flowName] ?? null;

      if (!is_array($flow)) {
        return 'flow_not_configured';
      }

      $processorName = (string) ($flow['processor'] ?? 'text');
      $options = is_array($flow['processor_options'] ?? null) ? $flow['processor_options'] : [];
      $value = $this->processors->get($processorName)->process($message->text, $options);

      if ($value === null || $value === '') {
        $this->logger->info('Value rejected by processor', [
          'flow' => $flowName,
          'messageId' => $message->messageId,
        ]);

        return 'value_rejected';
      }

      $this->logger->info('Value received', [
        'flow' => $flowName,
        'value' => $value,
      ]);

      $this->dealService->updateDeal(
        (int) $match->dealId,
        $flow,
        [(string) $flow['target_field'] => $value]
      );

      $this->logger->info('Deal updated', [
        'deal_id' => $match->dealId,
        'flow' => $flowName,
        'stage' => $flow['after_stage'],
      ]);

      return 'deal_updated';
    }

    return 'ignored';
  }
}
