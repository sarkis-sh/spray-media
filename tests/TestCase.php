<?php

namespace SprayMedia\Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithContainer;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use SprayMedia\Providers\SprayMediaServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    use InteractsWithContainer;
    use InteractsWithExceptionHandling;
    use InteractsWithSession;
    use MakesHttpRequests;

    protected function getPackageProviders($app)
    {
        return [SprayMediaServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Basic app key for encrypter/signing.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // In-memory sqlite for tests.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Keep media config predictable for tests.
        $app['config']->set('spray-media.disk', 'local');
        $app['config']->set('spray-media.base_dir', 'uploads');
        $app['config']->set('spray-media.route.prefix', 'media-items');
        $app['config']->set('spray-media.route.middleware_public', []);
        $app['config']->set('spray-media.route.middleware_admin', []);
        $app['config']->set('spray-media.hmac.secret', 'test-secret');
        $app['config']->set('spray-media.hmac.algorithm', 'sha256');
        $app['config']->set('spray-media.performance.cache_control', 'no-cache');
        $app['config']->set('spray-media.hmac.default_expiration_minutes', 60);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Run package migration.
        $this->loadMigrationsFrom(__DIR__.'/../src/Database/Migrations');

        // Fresh storage for each test.
        Config::set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/local'),
        ]);
    }
}
