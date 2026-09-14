<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Public-only fixtures: the signing key was discarded outside this project.
        // Exercise real verification throughout business tests without a production bypass.
        config([
            'license.public_key' => base_path('tests/Fixtures/licenses/public.key'),
            'license.path' => base_path('tests/Fixtures/licenses/lifetime.dat'),
        ]);
        $this->app->instance(\App\Services\Licensing\MachineIdentity::class, new class extends \App\Services\Licensing\MachineIdentity {
            public function id(): string { return str_repeat('A', 64); }
        });
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        if (getenv('INTEGRITY_MYSQL') === '1') {
            // Service fixtures and HTTP requests must use the same shop timezone.
            \App\Services\Settings::flush();
            $timezone = \App\Services\Settings::timezone();
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }

    public function createApplication()
    {
        $app=parent::createApplication();
        if (getenv('INTEGRITY_MYSQL')==='1') {
            $app['config']->set('database.default','mysql');
            $app['config']->set('database.connections.mysql.database','atelier_integrity_test');
            $app['config']->set('database.connections.mysql.url',null);
            $app['db']->purge('mysql');
        }
        return $app;
    }
}
