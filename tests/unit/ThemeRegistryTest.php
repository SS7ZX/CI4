<?php

declare(strict_types=1);

use App\Services\ThemeRegistry;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ThemeRegistryTest extends CIUnitTestCase
{
    public function testRegistryLoadsNineThemesWithAtsThemesFirst(): void
    {
        $themes = (new ThemeRegistry())->all();

        $this->assertCount(9, $themes);
        $this->assertSame(
            ['academic', 'ats-classic', 'compact', 'minimal', 'modern', 'editorial', 'executive', 'professional', 'split'],
            array_column($themes, 'key'),
        );
        $this->assertSame(
            [true, true, true, true, true, false, false, false, false],
            array_column($themes, 'ats_friendly'),
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
