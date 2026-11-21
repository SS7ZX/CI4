<?php

declare(strict_types=1);

namespace App\Services;

final class CvAiSanitizer
{
    /**
     * @param array<string, string> $personalData
     */
    public function sanitize(string $story, array $personalData): string
    {
        foreach ($personalData as $value) {
            $value = trim($value);
            if ($value !== '') {
                $story = str_ireplace($value, '[disamarkan]', $story);
            }
        }

        $story = preg_replace(
            '/(?<![\w.+-])[\w.!#$%&\'*+\/=?^_`{|}~-]+@[\w-]+(?:\.[\w-]+)+(?![\w-])/u',
            '[email disamarkan]',
            $story,
        ) ?? $story;
        $story = preg_replace(
            '/(?<!\w)(?:\+?62|0)(?:[\s().-]*\d){8,13}(?!\w)/u',
            '[nomor telepon disamarkan]',
            $story,
        ) ?? $story;
        $story = preg_replace(
            '/\b(?:jalan|jl\.?|jln\.?)\s+[^,\r\n]+?(?=\s+\b(?:tinggal|berdomisili|mengelola|bekerja|memiliki|membuat|menyusun|mengembangkan)\b|[,;\r\n]|$)/iu',
            '[alamat disamarkan]',
            $story,
        ) ?? $story;

        return trim($story);
    }
}
