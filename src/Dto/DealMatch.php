<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class DealMatch
{
  /** @param list<int> $dealIds */
  public function __construct(
    public string $status,
    public ?int $dealId = null,
    public array $dealIds = [],
    public ?string $flowName = null,
  ) {
  }

  public static function none(): self
  {
    return new self('none');
  }

  public static function single(int $dealId, string $flowName): self
  {
    return new self('single', $dealId, [], $flowName);
  }

  /** @param list<int> $dealIds */
  public static function multiple(array $dealIds, string $flowName): self
  {
    return new self('multiple', null, $dealIds, $flowName);
  }
}
