<?php

declare(strict_types=1);

namespace App\Branch;

use App\Config;
use App\Dto\Branch;

final class BranchResolver
{
  public function __construct(private readonly Config $config)
  {
  }

  public function resolve(string $rawCity): ?Branch
  {
    $normalized = $this->normalize($rawCity);

    if ($normalized === '') {
      return null;
    }

    foreach ($this->config->branches() as $key => $branch) {
      if (!is_array($branch)) {
        continue;
      }

      $cities = $branch['cities'] ?? [];
      if (!is_array($cities)) {
        continue;
      }

      foreach ($cities as $cityAlias) {
        if ($this->normalize((string) $cityAlias) === $normalized) {
          return new Branch(
            key: (string) $key,
            name: (string) $branch['name'],
            cities: array_map('strval', $cities),
            categoryId: (int) $branch['category_id'],
            stageId: (string) $branch['stage_id'],
            assignedById: isset($branch['assigned_by_id']) && $branch['assigned_by_id'] !== null && $branch['assigned_by_id'] !== ''
              ? (int) $branch['assigned_by_id']
              : null,
          );
        }
      }
    }

    return null;
  }

  public function normalize(string $text): string
  {
    $text = trim($text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    $text = rtrim($text, " \t\n\r\0\x0B.,;:!");

    return mb_strtolower($text);
  }

  public function validateBranchStage(Branch $branch): ?string
  {
    if ($branch->categoryId > 0 && !str_starts_with($branch->stageId, 'C' . $branch->categoryId . ':')) {
      return sprintf(
        'Stage "%s" may not belong to category_id=%d for branch "%s"',
        $branch->stageId,
        $branch->categoryId,
        $branch->key
      );
    }

    return null;
  }
}
