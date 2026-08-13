<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class Branch
{
  /**
   * @param list<string> $cities
   */
  public function __construct(
    public string $key,
    public string $name,
    public array $cities,
    public int $categoryId,
    public string $stageId,
    public ?int $assignedById,
  ) {
  }
}
