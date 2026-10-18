<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use UnexpectedValueException;

final class ThemeRegistry
{
    private const LAYOUTS = ['single-column', 'sidebar', 'compact', 'editorial', 'split'];
    private const FONT_FAMILIES = ['CVMaker Sans'];
    private const CACHE_TTL = 300;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $themes = null;

    public function __construct(private readonly string $viewsPath = APPPATH . 'Views')
    {
    }

    /**
     * @return list<array{key: string, name_key: string, description_key: string, ats_friendly: bool, layout: string, font_family: string, accent_palette: list<string>}>
     */
    public function all(): array
    {
        if ($this->themes !== null) {
            return array_values($this->themes);
        }

        $themeFiles = glob($this->viewsPath . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'theme.json');
        if ($themeFiles === false) {
            throw new RuntimeException('Daftar tema CV tidak dapat dibaca.');
        }

        $fingerprintParts = [];
        foreach ($themeFiles as $themeFile) {
            $fingerprintParts[] = $themeFile;
            $contents = file_get_contents($themeFile);
            if ($contents === false) {
                throw new RuntimeException('File konfigurasi tema tidak dapat dibaca: ' . $themeFile);
            }
            $fingerprintParts[] = hash('sha256', $contents);
            foreach (['template.php', 'style.css'] as $requiredFile) {
                $fingerprintParts[] = (string) is_file(dirname($themeFile) . DIRECTORY_SEPARATOR . $requiredFile);
            }
        }
        $cacheKey = 'cv-themes-' . hash('sha256', implode('|', $fingerprintParts));
        $cachedThemes = cache()->get($cacheKey);
        if (is_array($cachedThemes) && $this->isThemeList($cachedThemes)) {
            $this->themes = array_column($cachedThemes, null, 'key');

            return $cachedThemes;
        }

        $themes = [];
        foreach ($themeFiles as $themeFile) {
            $theme = $this->readTheme($themeFile);
            if (isset($themes[$theme['key']])) {
                throw new UnexpectedValueException('Kunci tema CV terduplikasi: ' . $theme['key']);
            }
            $themes[$theme['key']] = $theme;
        }

        if ($themes === []) {
            throw new RuntimeException('Tidak ada tema CV yang tersedia.');
        }

        uasort($themes, static fn (array $left, array $right): int => ($right['ats_friendly'] <=> $left['ats_friendly']) ?: ($left['key'] <=> $right['key']));
        $this->themes = $themes;
        $themeList = array_values($themes);
        if (! cache()->save($cacheKey, $themeList, self::CACHE_TTL)) {
            log_message('warning', 'Daftar tema CV tidak dapat disimpan ke cache.');
        }

        return $themeList;
    }

    /**
     * @return array{key: string, name_key: string, description_key: string, ats_friendly: bool, layout: string, font_family: string, accent_palette: list<string>, template_path: string, stylesheet_path: string}
     */
    public function get(string $key): array
    {
        foreach ($this->all() as $theme) {
            if ($theme['key'] === $key) {
                $directory = $this->viewsPath . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $key;

                return [
                    ...$theme,
                    'template_path'   => $directory . DIRECTORY_SEPARATOR . 'template.php',
                    'stylesheet_path' => $directory . DIRECTORY_SEPARATOR . 'style.css',
                ];
            }
        }

        throw new InvalidArgumentException('Tema CV tidak ditemukan: ' . $key);
    }

    public function has(string $key): bool
    {
        try {
            $this->get($key);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @return array{key: string, name_key: string, description_key: string, ats_friendly: bool, layout: string, font_family: string, accent_palette: list<string>}
     */
    private function readTheme(string $themeFile): array
    {
        $json = file_get_contents($themeFile);
        if ($json === false) {
            throw new RuntimeException('File konfigurasi tema tidak dapat dibaca: ' . $themeFile);
        }

        try {
            $theme = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('File konfigurasi tema bukan JSON yang valid: ' . $themeFile, 0, $exception);
        }

        $directory = basename(dirname($themeFile));
        if (
            ! is_array($theme)
            || array_is_list($theme)
            || ! isset($theme['key'], $theme['name_key'], $theme['description_key'], $theme['ats_friendly'], $theme['layout'], $theme['font_family'])
            || ! is_string($theme['key'])
            || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $theme['key']) !== 1
            || $theme['key'] !== $directory
            || ! is_string($theme['name_key'])
            || preg_match('/\AThemes\.[a-z0-9_]+\z/', $theme['name_key']) !== 1
            || ! is_string($theme['description_key'])
            || preg_match('/\AThemes\.[a-z0-9_]+\z/', $theme['description_key']) !== 1
            || ! is_bool($theme['ats_friendly'])
            || ! is_string($theme['layout'])
            || ! in_array($theme['layout'], self::LAYOUTS, true)
            || ! is_string($theme['font_family'])
            || ! in_array($theme['font_family'], self::FONT_FAMILIES, true)
            || ! isset($theme['accent_palette'])
            || ! is_array($theme['accent_palette'])
            || ! array_is_list($theme['accent_palette'])
            || count($theme['accent_palette']) < 3
            || count($theme['accent_palette']) > 8
        ) {
            throw new UnexpectedValueException('File konfigurasi tema tidak memenuhi format yang diwajibkan: ' . $themeFile);
        }

        foreach ($theme['accent_palette'] as $color) {
            if (! is_string($color) || preg_match('/\A#[0-9a-fA-F]{6}\z/', $color) !== 1) {
                throw new UnexpectedValueException('Palet warna tema tidak valid: ' . $themeFile);
            }
        }
        if (count(array_unique(array_map('strtolower', $theme['accent_palette']))) !== count($theme['accent_palette'])) {
            throw new UnexpectedValueException('Palet warna tema tidak boleh memiliki warna duplikat: ' . $themeFile);
        }

        foreach (['template.php', 'style.css'] as $requiredFile) {
            if (! is_file(dirname($themeFile) . DIRECTORY_SEPARATOR . $requiredFile) || ! is_readable(dirname($themeFile) . DIRECTORY_SEPARATOR . $requiredFile)) {
                throw new UnexpectedValueException('File tema wajib tidak tersedia: ' . $themeFile);
            }
        }

        return [
            'key'             => $theme['key'],
            'name_key'        => $theme['name_key'],
            'description_key' => $theme['description_key'],
            'ats_friendly'    => $theme['ats_friendly'],
            'layout'          => $theme['layout'],
            'font_family'     => $theme['font_family'],
            'accent_palette'  => $theme['accent_palette'],
        ];
    }

    /**
     * @param array<mixed> $themes
     */
    private function isThemeList(array $themes): bool
    {
        if (! array_is_list($themes) || $themes === []) {
            return false;
        }

        foreach ($themes as $theme) {
            if (
                ! is_array($theme)
                || ! isset($theme['key'], $theme['name_key'], $theme['description_key'], $theme['ats_friendly'], $theme['layout'], $theme['font_family'], $theme['accent_palette'])
                || ! is_string($theme['key'])
                || ! is_string($theme['name_key'])
                || ! is_string($theme['description_key'])
                || ! is_bool($theme['ats_friendly'])
                || ! is_string($theme['layout'])
                || ! is_string($theme['font_family'])
                || ! is_array($theme['accent_palette'])
                || ! array_is_list($theme['accent_palette'])
                || count($theme['accent_palette']) < 3
                || count($theme['accent_palette']) > 8
            ) {
                return false;
            }
            foreach ($theme['accent_palette'] as $color) {
                if (! is_string($color) || preg_match('/\A#[0-9a-fA-F]{6}\z/', $color) !== 1) {
                    return false;
                }
            }
        }

        return true;
    }
}
