<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use App\Libraries\Jobs\Dto\SalaryRange;

final class SalaryParser
{
    public function parse(?string $salary): SalaryRange
    {
        if ($salary === null || trim($salary) === '') {
            return new SalaryRange(null, null, null, false);
        }
        $text = mb_strtolower($salary, 'UTF-8');
        if (preg_match('/\b(?:per\s+hour|hourly|per\s+day|daily|per\s+week|weekly|per\s+hari|per\s+jam|per\s+minggu)\b/u', $text) === 1) {
            return new SalaryRange(null, null, null, false);
        }

        preg_match_all('/\d[\d.,]*/u', $text, $matches);
        if ($matches[0] === []) {
            return new SalaryRange(null, null, null, false);
        }
        $values = array_map(fn (string $value): ?int => $this->parseNumber($value), array_slice($matches[0], 0, 2));
        if (in_array(null, $values, true)) {
            return new SalaryRange(null, null, null, false);
        }

        $currency = preg_match('/\b(?:idr|rp\.?)\b/u', $text) === 1 || str_contains($text, 'rp')
            ? 'IDR'
            : (preg_match('/\b(?:usd|dollar)\b/u', $text) === 1 || str_contains($text, '$') ? 'USD' : null);
        $yearly = preg_match('/\b(?:per\s+year|annual|annually|per\s+annum|per\s+tahun)\b/u', $text) === 1;
        if ($currency !== 'IDR') {
            return new SalaryRange(null, null, $currency, false);
        }

        $minimum = $values[0];
        $maximum = $values[1] ?? $minimum;
        $multiplier = match (true) {
            preg_match('/\b(?:juta|jt|million)\b/u', $text) === 1 => 1_000_000,
            preg_match('/\b(?:ribu|rb|thousand)\b/u', $text) === 1 => 1_000,
            default => 1,
        };
        if ($multiplier > 1 && max($minimum, $maximum) < 10_000) {
            $minimum *= $multiplier;
            $maximum *= $multiplier;
        }
        if ($yearly) {
            $minimum = (int) round($minimum / 12);
            $maximum = (int) round($maximum / 12);
        }

        return new SalaryRange(min($minimum, $maximum), max($minimum, $maximum), $currency, true);
    }

    private function parseNumber(string $raw): ?int
    {
        $normalized = preg_replace('/[.,](?=\d{3}(?:\D|$))/', '', $raw) ?? '';
        if (str_contains($normalized, ',') || str_contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
            if (! is_numeric($normalized)) {
                return null;
            }
            $number = (float) $normalized;
        } else {
            $number = (float) $normalized;
        }
        if ($number < 0 || $number > 2_000_000_000) {
            return null;
        }

        return (int) round($number);
    }
}
