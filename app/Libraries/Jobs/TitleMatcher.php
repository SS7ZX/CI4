<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

final class TitleMatcher
{
    /**
     * @param list<array{canonical_title: string, aliases: list<string>, job_family?: string, seniority_hint?: string|null}> $dictionary
     *
     * @return array{canonical_title: string, aliases: list<string>, job_family: string, seniority_hint: string|null}|null
     */
    public function match(string $title, array $dictionary): ?array
    {
        $normalizedTitle = SkillExtractor::normalize($title);
        if ($normalizedTitle === '') {
            return null;
        }

        foreach ($dictionary as $entry) {
            $canonical = trim($entry['canonical_title'] ?? '');
            if ($canonical === '') {
                continue;
            }
            foreach ([$canonical, ...($entry['aliases'] ?? [])] as $alias) {
                if (SkillExtractor::normalize((string) $alias) === $normalizedTitle) {
                    return [
                        'canonical_title' => $canonical,
                        'aliases'         => array_values(array_unique(array_map(
                            static fn (mixed $value): string => trim((string) $value),
                            $entry['aliases'] ?? [],
                        ))),
                        'job_family'      => (string) ($entry['job_family'] ?? ''),
                        'seniority_hint'  => isset($entry['seniority_hint']) ? (string) $entry['seniority_hint'] : null,
                    ];
                }
            }
        }

        return null;
    }
}
