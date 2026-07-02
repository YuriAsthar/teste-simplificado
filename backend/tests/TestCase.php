<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;
use Override;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->forceTestingEnvironment();
    }

    #[Override]
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Override]
    public function createApplication()
    {
        $this->forceTestingEnvironment();

        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    private function forceTestingEnvironment(): void
    {
        $values = [
            'APP_ENV' => 'testing',
            'APP_URL' => 'http://localhost',
        ];

        foreach ($values as $key => $value) {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
