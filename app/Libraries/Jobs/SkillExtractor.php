<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

final class SkillExtractor
{
    /**
     * @param list<array{canonical: string, aliases: list<string>, category?: string, weight?: int}> $dictionary
     *
     * @return array<string, array{canonical: string, category: string, weight: int, occurrences: int}>
     */
    public function extract(string $text, array $dictionary): array
    {
        $normalizedText = self::normalize($text);
        if ($normalizedText === '') {
            return [];
        }

        $matches = [];
        foreach ($dictionary as $entry) {
            $canonical = trim($entry['canonical'] ?? '');
            if ($canonical === '') {
                continue;
            }

            $aliases = array_values(array_unique([
                $canonical,
                ...($entry['aliases'] ?? []),
            ]));
            $occurrences = 0;
            foreach ($aliases as $alias) {
                $normalizedAlias = self::normalize((string) $alias);
                if ($normalizedAlias === '') {
                    continue;
                }
                $count = preg_match_all(
                    '/(?<![\pL\pN])' . preg_quote($normalizedAlias, '/') . '(?![\pL\pN])/u',
                    $normalizedText,
                );
                $occurrences = max($occurrences, $count === false ? 0 : $count);
            }

            if ($occurrences > 0) {
                $matches[$canonical] = [
                    'canonical'   => $canonical,
                    'category'    => (string) ($entry['category'] ?? ''),
                    'weight'      => max(1, (int) ($entry['weight'] ?? 1)),
                    'occurrences' => $occurrences,
                ];
            }
        }

        return $matches;
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\pL\pN]+/u', ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
