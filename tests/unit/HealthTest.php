<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Tests\Support\Libraries\ConfigReader;

/**
 * @internal
 */
final class HealthTest extends CIUnitTestCase
{
    public function testIndonesianValidationUniqueMessageIsAvailable(): void
    {
        $message = lang('Validation.is_unique');

        $this->assertSame(
            'Nilai pada kolom {field} sudah digunakan. Gunakan nilai yang berbeda.',
            $message,
        );
    }

    public function testSameOriginUrlUsesOnlyPathAndQuery(): void
    {
        $this->assertSame(
            '/dashboard/cv/example?page=2',
            same_origin_url('http://127.0.0.1:8080/dashboard/cv/example?page=2'),
        );
    }

    public function testIsDefinedAppPath(): void
    {
        $this->assertDirectoryExists(APPPATH);
    }

    public function testBaseUrlHasBeenSet(): void
    {
        $validation = service('validation');

        $env = false;

        // Check the baseURL in .env
        if (is_file(HOMEPATH . '.env')) {
            $env = preg_grep('/^app\.baseURL = ./', file(HOMEPATH . '.env')) !== false;
        }

        if ($env) {
            // BaseURL in .env is a valid URL?
            // phpunit.dist.xml sets app.baseURL in $_SERVER
            // So if you set app.baseURL in .env, it takes precedence
            $config = new App();
            $this->assertTrue(
                $validation->check($config->baseURL, 'valid_url'),
                'baseURL "' . $config->baseURL . '" in .env is not valid URL',
            );
        }

        // Get the baseURL in app/Config/App.php
        // You can't use Config\App, because phpunit.dist.xml sets app.baseURL
        $reader = new ConfigReader();

        // BaseURL in app/Config/App.php is a valid URL?
        $this->assertTrue(
            $validation->check($reader->baseURL, 'valid_url'),
            'baseURL "' . $reader->baseURL . '" in app/Config/App.php is not valid URL',
        );
    }
}
