<?php

declare(strict_types=1);

namespace App;

use App\Contract\ValueProcessorInterface;

/**
 * Text value processor for city flow (and reusable for similar text flows).
 */
final class CityProcessor implements ValueProcessorInterface
{
  /** @param array<string, mixed> $options */
  public function process(string $rawText, array $options = []): ?string
  {
    $text = trim($rawText);

    if ($text === '') {
      return null;
    }

    $saveRaw = (bool) ($options['save_raw_text'] ?? true);
    $normalize = (bool) ($options['normalize'] ?? false);
    $aliases = is_array($options['aliases'] ?? null) ? $options['aliases'] : [];
    $allowed = is_array($options['allowed_cities'] ?? null) ? $options['allowed_cities'] : [];
    $unknownBehavior = (string) ($options['unknown_city_behavior'] ?? 'save_raw');

    if (!$normalize && empty($aliases) && empty($allowed)) {
      return $saveRaw ? $text : null;
    }

    $candidate = $text;

    if ($normalize || !empty($aliases)) {
      $candidate = $this->applyAliases($text, $aliases);
    }

    if (!empty($allowed)) {
      $allowedNormalized = array_map(
        static fn(string $city): string => mb_strtolower(trim($city)),
        $allowed
      );

      if (!in_array(mb_strtolower($candidate), $allowedNormalized, true)) {
        return match ($unknownBehavior) {
          'reject' => null,
          default => $saveRaw ? $text : $candidate,
        };
      }
    }

    return $candidate;
  }

  /** @param array<string, string> $aliases */
  private function applyAliases(string $text, array $aliases): string
  {
    $key = mb_strtolower(trim($text));

    foreach ($aliases as $alias => $canonical) {
      if (mb_strtolower((string) $alias) === $key) {
        return (string) $canonical;
      }
    }

    return $text;
  }
}
