<?php

declare(strict_types=1);

namespace App;

use App\Bitrix\BitrixClient;
use App\Bitrix\DealService;
use App\Exception\BitrixApiException;
use App\Flow\FlowOrchestrator;
use App\Flow\ValueProcessorRegistry;
use App\Http\JsonResponse;
use App\Http\WebhookSecurity;
use App\Wazzup\WazzupWebhookParser;
use Psr\Log\LoggerInterface;
use Throwable;

final class WazzupWebhookHandler
{
  public function __construct(
    private readonly WebhookSecurity $security,
    private readonly WazzupWebhookParser $parser,
    private readonly FlowOrchestrator $orchestrator,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function handle(): void
  {
    try {
      if ($error = $this->security->validateRequestMethod()) {
        JsonResponse::send(405, ['ok' => false, 'error' => $error]);

        return;
      }

      if ($error = $this->security->validateAccessKey()) {
        JsonResponse::send(403, ['ok' => false, 'error' => $error]);

        return;
      }

      if ($error = $this->security->validateContentType()) {
        JsonResponse::send(415, ['ok' => false, 'error' => $error]);

        return;
      }

      $rawBody = $this->security->readBody();

      if ($rawBody === '') {
        JsonResponse::send(400, ['ok' => false, 'error' => 'Empty body']);

        return;
      }

      try {
        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
      } catch (\JsonException) {
        $this->logger->warning('Wazzup payload error: invalid JSON');
        JsonResponse::send(400, ['ok' => false, 'error' => 'Invalid JSON']);

        return;
      }

      if (!is_array($payload)) {
        JsonResponse::send(400, ['ok' => false, 'error' => 'Invalid payload']);

        return;
      }

      $this->logger->info('Webhook received', [
        'keys' => array_keys($payload),
      ]);

      if ($this->parser->isTestRequest($payload)) {
        JsonResponse::send(200, ['ok' => true]);

        return;
      }

      $messages = $this->parser->extractIncomingTextMessages($payload);

      if ($messages === []) {
        JsonResponse::send(200, ['ok' => true, 'processed' => 0]);

        return;
      }

      $processedCount = 0;

      foreach ($messages as $message) {
        try {
          $result = $this->orchestrator->processMessage($message);

          if ($result === 'deal_updated') {
            $processedCount++;
          }
        } catch (BitrixApiException $e) {
          $this->logger->error('Bitrix API error during webhook processing', [
            'messageId' => $message->messageId,
            'error' => $e->bitrixError,
          ]);
        } catch (Throwable $e) {
          $this->logger->error('Unexpected processing error', [
            'messageId' => $message->messageId,
            'message' => $e->getMessage(),
          ]);
        }
      }

      JsonResponse::send(200, ['ok' => true, 'processed' => $processedCount]);
    } catch (Throwable $e) {
      $this->logger->error('Webhook handler failure', [
        'message' => $e->getMessage(),
      ]);

      JsonResponse::send(500, ['ok' => false]);
    }
  }

  public static function createFromConfig(Config $config): self
  {
    $logger = LoggerFactory::create($config);
    $phoneNormalizer = new PhoneNormalizer();
    $bitrix = new BitrixClient($config, $logger);
    $dealService = new DealService($config, $bitrix, $phoneNormalizer, $logger);

    return new self(
      new WebhookSecurity($config),
      new WazzupWebhookParser($config, $phoneNormalizer),
      new FlowOrchestrator(
        $config,
        $dealService,
        new ValueProcessorRegistry(),
        $logger
      ),
      $logger,
    );
  }
}
