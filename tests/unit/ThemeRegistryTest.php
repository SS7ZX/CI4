<?php

declare(strict_types=1);

use App\Services\ThemeRegistry;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ThemeRegistryTest extends CIUnitTestCase
{
    public function testRegistryLoadsFiveThemesWithAtsThemeFirst(): void
    {
        $themes = (new ThemeRegistry())->all();

        $this->assertCount(5, $themes);
        $this->assertSame('ats-classic', $themes[0]['key']);
        $this->assertTrue($themes[0]['ats_friendly']);
        $this->assertSame('compact', $themes[1]['key']);
        $this->assertTrue($themes[1]['ats_friendly']);
        $this->assertSame(
            ['ats-classic', 'compact', 'editorial', 'professional', 'split'],
            array_column($themes, 'key'),
        );
    }

    public function testRegistryRejectsInvalidThemeJson(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cv-maker-theme-' . bin2hex(random_bytes(8));
        $directory = $root . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'broken';
        $this->assertTrue(mkdir($directory, 0700, true));
        $themeFile = $directory . DIRECTORY_SEPARATOR . 'theme.json';
        $this->assertNotFalse(file_put_contents($themeFile, '{"key":"different-key"}'));

        try {
            $this->expectException(\UnexpectedValueException::class);
            (new ThemeRegistry($root))->all();
        } finally {
            unlink($themeFile);
            rmdir($directory);
            rmdir(dirname($directory));
            rmdir($root);
        }
    }
}
