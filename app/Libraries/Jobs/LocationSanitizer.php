<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

final class LocationSanitizer
{
    public function normalizeCity(string $value): ?string
    {
        $city = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if (
            $city === ''
            || mb_strlen($city, 'UTF-8') > 120
            || preg_match('/[\d@,;\/\\\\]/u', $city) === 1
            || preg_match('/\b(?:jalan|jl|street|st|rt|rw|blok|block|nomor|no|unit|apartemen|apartment|lantai|floor|kode pos|postal code)\b/iu', $city) === 1
            || preg_match('/\bhttps?:/iu', $city) === 1
        ) {
            return null;
        }

        return $city;
    }
}
