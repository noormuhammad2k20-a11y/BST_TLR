<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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
