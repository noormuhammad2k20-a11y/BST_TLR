<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
