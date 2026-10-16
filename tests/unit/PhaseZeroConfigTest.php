<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Database;

/**
 * @internal
 */
final class PhaseZeroConfigTest extends CIUnitTestCase
{
    public function testAppUsesIndonesianLocaleAndJakartaTimezone(): void
    {
        $app = new App();

        $this->assertSame('id', $app->defaultLocale);
        $this->assertSame(['id'], $app->supportedLocales);
        $this->assertSame('Asia/Jakarta', $app->appTimezone);
    }

    public function testDefaultDatabaseUsesPostgreWithSsl(): void
    {
        $database = new Database();

        $this->assertSame('Postgre', $database->default['DBDriver']);
        $this->assertSame(5432, $database->default['port']);
        $this->assertContains($database->default['sslmode'], ['require', 'verify-ca', 'verify-full']);
    }
}
