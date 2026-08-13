<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class LeadMatch
{
  /** @param list<int> $leadIds */
  public function __construct(
    public string $status,
    public ?int $leadId = null,
    public array $leadIds = [],
    /** @var array<string, mixed>|null */
    public ?array $lead = null,
  ) {
  }

  public static function none(): self
  {
    return new self('none');
  }

  /** @param array<string, mixed> $lead */
  public static function single(int $leadId, array $lead): self
  {
    return new self('single', $leadId, [], $lead);
  }

  /** @param list<int> $leadIds */
  public static function multiple(array $leadIds): self
  {
    return new self('multiple', null, $leadIds);
  }
}
