<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class HomeLayoutTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    public function testHomePageRendersResponsiveBaseLayout(): void
    {
        $result = $this->get('/');

        $result->assertOK();
        $this->assertStringContainsString('<html lang="id">', $result->getBody());
        $result->assertSee('assets/css/app.css');
        $result->assertSee('assets/js/alpine.min.js');
        $result->assertSee('Buat CV secara bertahap');
    }
}
