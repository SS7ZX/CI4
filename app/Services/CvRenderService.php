<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Cv as CvEntity;
use App\Entities\CvProfile;
use CodeIgniter\Exceptions\PageNotFoundException;
use InvalidArgumentException;
use RuntimeException;

final class CvRenderService
{
    private const SECTION_MAP = [
        'profile'         => 'full_name',
        'summary'         => 'summary_label',
        'experience'      => 'experiences',
        'education'       => 'educations',
        'skills'          => 'skills',
        'links'           => 'links',
        'projects'        => 'projects',
        'certifications'  => 'certifications',
        'organizations'   => 'organizations',
        'awards'          => 'awards',
        'languages'       => 'languages',
        'custom_sections' => 'custom_sections',
    ];

    public function __construct(
        private readonly CvBuilderService $builder = new CvBuilderService(),
        private readonly ThemeRegistry $themes = new ThemeRegistry(),
    ) {
    }

    /**
     * @return array{cv: array<string, mixed>, profile: array<string, mixed>, sections: list<array{key: string, label: string, visible: bool, content: mixed, items: list<array<string, mixed>>}>}
     */
    public function buildDocument(int|string $userId, string $cvId): array
    {
        $loaded = $this->builder->load($userId, $cvId);
        $cv     = $loaded['cv'];
        if (! $cv instanceof CvEntity) {
            throw new RuntimeException('Data CV yang dimuat tidak valid.');
        }

        $profile = $loaded['profile'] instanceof CvProfile
            ? $loaded['profile']->toRawArray()
            : [];
        $sectionsByKey = [];
        foreach ($loaded['sections'] as $setting) {
            $raw = $setting->toRawArray();
            $sectionsByKey[$raw['section_key']] = [
                'visible'   => filter_var($raw['is_visible'], FILTER_VALIDATE_BOOLEAN),
                'sort_order'=> (int) $raw['sort_order'],
            ];
        }

        $orderedKeys = array_keys(self::SECTION_MAP);
        if ($sectionsByKey !== []) {
            $defaultPosition = array_flip($orderedKeys);
            usort($orderedKeys, static function (string $left, string $right) use ($sectionsByKey, $defaultPosition): int {
                $leftOrder  = $sectionsByKey[$left]['sort_order'] ?? PHP_INT_MAX;
                $rightOrder = $sectionsByKey[$right]['sort_order'] ?? PHP_INT_MAX;

                return ($leftOrder <=> $rightOrder) ?: ($defaultPosition[$left] <=> $defaultPosition[$right]);
            });
        }

        $sections = [];
        foreach ($orderedKeys as $key) {
            $collection = match ($key) {
                'profile' => [],
                'summary' => [],
                'experience' => 'experiences',
                'education' => 'educations',
                'skills' => 'skill_groups',
                'links' => 'links',
                'projects' => 'projects',
                'certifications' => 'certifications',
                'organizations' => 'organizations',
                'awards' => 'awards',
                'languages' => 'languages',
                'custom_sections' => 'custom_sections',
            };
            $items = is_string($collection)
                ? array_map(static fn (object $entity): array => $entity->toRawArray(), $loaded[$collection])
                : [];

            foreach ($items as &$item) {
                foreach (['bullets', 'items', 'tech_stack'] as $jsonField) {
                    if (isset($item[$jsonField]) && is_string($item[$jsonField])) {
                        $decoded = json_decode($item[$jsonField], true);
                        $item[$jsonField] = is_array($decoded) ? $decoded : [];
                    }
                }
            }
            unset($item);

            $content = match ($key) {
                'profile' => $profile,
                'summary' => $profile['summary'] ?? '',
                default => $items,
            };

            $sections[] = [
                'key'      => $key,
                'label'    => lang('CvRender.' . self::SECTION_MAP[$key]),
                'visible'  => $sectionsByKey[$key]['visible'] ?? true,
                'content'  => $content,
                'items'    => $items,
            ];
        }

        return [
            'cv'       => $cv->toRawArray(),
            'profile'  => $profile,
            'sections' => $sections,
        ];
    }

    public function renderHtml(int|string $userId, string $cvId, ?string $themeKey = null): string
    {
        return $this->renderDocumentHtml($this->buildDocument($userId, $cvId), $themeKey);
    }

    /**
     * @param array{cv: array<string, mixed>, profile: array<string, mixed>, sections: list<array<string, mixed>>} $document
     */
    public function renderDocumentHtml(array $document, ?string $themeKey = null): string
    {
        $themeKey ??= (string) ($document['cv']['theme_key'] ?? '');
        try {
            $theme = $this->themes->get($themeKey);
        } catch (InvalidArgumentException) {
            throw PageNotFoundException::forPageNotFound();
        }

        $css = file_get_contents($theme['stylesheet_path']);
        if ($css === false) {
            throw new RuntimeException('Stylesheet tema tidak dapat dibaca: ' . $themeKey);
        }

        $accentColor = $document['cv']['accent_color'] ?? null;
        if (! is_string($accentColor) || ! in_array(strtolower($accentColor), array_map('strtolower', $theme['accent_palette']), true)) {
            $accentColor = $theme['accent_palette'][0];
        }
        $scale = match ($document['cv']['font_scale'] ?? 'medium') {
            'small' => 0.9,
            'large' => 1.1,
            default => 1.0,
        };
        $css .= sprintf(
            "\n.cv-document{font-size:%.2fpt}.cv-header h1{font-size:%.2fpt}.cv-headline{font-size:%.2fpt}.cv-contact,.cv-meta{font-size:%.2fpt}.cv-section h2{font-size:%.2fpt}.cv-entry h3{font-size:%.2fpt}.cv-subtitle{font-size:%.2fpt}.cv-section h2,.cv-document a{color:%s}.cv-section h2{border-color:%s}.cv-header{border-color:%s}\n",
            10.0 * $scale,
            22.0 * $scale,
            12.0 * $scale,
            9.0 * $scale,
            11.5 * $scale,
            10.5 * $scale,
            10.0 * $scale,
            $accentColor,
            $accentColor,
            $accentColor,
        );

        $content = view('themes/' . $themeKey . '/template', [
            'document' => $document,
            'theme'    => $theme,
        ]);

        return view('themes/document', [
            'content' => $content,
            'css'     => $css,
            'theme'   => $theme,
            'paper'   => $document['cv']['paper_size'] ?? 'a4',
        ]);
    }
}
