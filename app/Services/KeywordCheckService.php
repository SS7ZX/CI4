<?php

declare(strict_types=1);

namespace App\Services;

final class KeywordCheckService
{
    private const EXCLUDED_FIELDS = [
        'address',
        'city',
        'company',
        'email',
        'full_name',
        'institution',
        'issuer',
        'location',
        'phone',
        'organization',
    ];

    /**
     * @param array<string, mixed> $cvData
     * @param list<string> $keywords
     * @param list<array{canonical: string, aliases: list<string>, category?: string}> $dictionary
     *
     * @return list<array{keyword: string, present: bool, sections: list<string>}>
     */
    public function check(array $cvData, array $keywords, array $dictionary): array
    {
        $searchableText = mb_strtolower(implode(' ', $this->collectText($cvData)), 'UTF-8');
        $dictionaryByCanonical = [];
        foreach ($dictionary as $entry) {
            $dictionaryByCanonical[mb_strtolower($entry['canonical'], 'UTF-8')] = $entry;
        }

        $checks = [];
        foreach (array_values(array_unique($keywords)) as $keyword) {
            $entry = $dictionaryByCanonical[mb_strtolower($keyword, 'UTF-8')] ?? null;
            $terms = array_values(array_unique([
                $keyword,
                ...($entry['aliases'] ?? []),
            ]));
            $present = false;
            foreach ($terms as $term) {
                if ($this->containsTerm($searchableText, $term)) {
                    $present = true;
                    break;
                }
            }

            $checks[] = [
                'keyword' => $keyword,
                'present' => $present,
                'sections' => $present ? [] : $this->suggestedSections((string) ($entry['category'] ?? '')),
            ];
        }

        return $checks;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function collectText(array $data): array
    {
        $text = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(mb_strtolower($key, 'UTF-8'), self::EXCLUDED_FIELDS, true)) {
                continue;
            }
            if (is_string($value) && $value !== '') {
                $text[] = $value;
            } elseif (is_array($value)) {
                $text = [...$text, ...$this->collectText($value)];
            }
        }

        return $text;
    }

    private function containsTerm(string $text, string $term): bool
    {
        if ($term === '') {
            return false;
        }
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote(mb_strtolower($term, 'UTF-8'), '/') . '(?![\p{L}\p{N}])/u';

        return preg_match($pattern, $text) === 1;
    }

    /**
     * @return list<string>
     */
    private function suggestedSections(string $category): array
    {
        return match (mb_strtolower($category, 'UTF-8')) {
            'technical', 'technology', 'programming', 'engineering', 'data', 'software', 'design' => ['skills', 'projects', 'experience'],
            'language', 'languages' => ['skills', 'certifications'],
            'business', 'management', 'finance', 'marketing', 'operations', 'communication', 'soft_skills' => ['experience', 'projects', 'skills'],
            default => ['experience', 'projects', 'skills'],
        };
    }
}
