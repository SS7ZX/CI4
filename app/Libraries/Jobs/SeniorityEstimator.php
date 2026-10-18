<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use DateTimeImmutable;
use DateTimeZone;

final class SeniorityEstimator
{
    /**
     * @param array{junior: int, mid: int, senior: int, lead: int}|null $boundaries
     */
    public function __construct(private readonly ?array $boundaries = null)
    {
        $values = $boundaries ?? ['junior' => 1, 'mid' => 3, 'senior' => 6, 'lead' => 10];
        if (
            ! isset($values['junior'], $values['mid'], $values['senior'], $values['lead'])
            || ! ($values['junior'] < $values['mid'] && $values['mid'] < $values['senior'] && $values['senior'] < $values['lead'])
        ) {
            throw new \InvalidArgumentException('Batas pengalaman harus berurutan naik.');
        }
    }

    /**
     * @param list<array<string, mixed>> $experiences
     *
     * @return array{years: float, level: string}
     */
    public function estimate(array $experiences, ?DateTimeImmutable $asOf = null): array
    {
        $today = $asOf ?? new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $intervals = [];

        foreach ($experiences as $experience) {
            $start = $this->parseDate($experience['start_date'] ?? null);
            $end   = ! empty($experience['is_current'])
                ? $today
                : ($this->parseDate($experience['end_date'] ?? null) ?? $start);
            if ($start === null || $start > $today || $end < $start) {
                continue;
            }

            $intervals[] = [$start->getTimestamp(), min($end, $today)->getTimestamp()];
        }

        usort($intervals, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $totalSeconds = 0;
        $currentStart = null;
        $currentEnd   = null;
        foreach ($intervals as [$start, $end]) {
            if ($currentStart === null) {
                $currentStart = $start;
                $currentEnd   = $end;
                continue;
            }
            if ($start <= $currentEnd) {
                $currentEnd = max($currentEnd, $end);
                continue;
            }
            $totalSeconds += $currentEnd - $currentStart;
            $currentStart = $start;
            $currentEnd   = $end;
        }
        if ($currentStart !== null && $currentEnd !== null) {
            $totalSeconds += $currentEnd - $currentStart;
        }

        $years = round($totalSeconds / 31556952, 2);
        $boundaries = $this->boundaries ?? ['junior' => 1, 'mid' => 3, 'senior' => 6, 'lead' => 10];

        return [
            'years' => $years,
            'level' => match (true) {
                $years < $boundaries['junior'] => 'fresh_graduate',
                $years < $boundaries['mid'] => 'junior',
                $years < $boundaries['senior'] => 'mid',
                $years < $boundaries['lead'] => 'senior',
                default => 'lead',
            },
        ];
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ? $date
            : null;
    }
}
