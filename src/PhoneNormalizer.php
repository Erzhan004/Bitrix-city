<?php

declare(strict_types=1);

namespace App;

final class PhoneNormalizer
{
  /**
   * Normalize phone to digits-only international form without unsafe transformations.
   * Examples: +7 707 123 45 67 → 77071234567, 87071234567 → 77071234567 (KZ local).
   */
  public function normalize(?string $raw): ?string
  {
    if ($raw === null) {
      return null;
    }

    $digits = preg_replace('/\D+/', '', trim($raw)) ?? '';

    if ($digits === '') {
      return null;
    }

    // Kazakhstan local mobile: 8XXXXXXXXXX (11 digits) → 7XXXXXXXXXX
    if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
      $digits = '7' . substr($digits, 1);
    }

    // Reasonable E.164 length without country-specific rewriting beyond KZ 8→7.
    if (strlen($digits) < 8 || strlen($digits) > 15) {
      return null;
    }

    return $digits;
  }

  /** @return list<string> */
  public function lookupVariants(string $normalized): array
  {
    $variants = [$normalized];

    if (str_starts_with($normalized, '7') && strlen($normalized) === 11) {
      $variants[] = '8' . substr($normalized, 1);
      $variants[] = '+' . $normalized;
    }

    return array_values(array_unique($variants));
  }
}
