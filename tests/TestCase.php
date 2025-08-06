<?php

namespace Dominservice\Invisible\Tests;

use Dominservice\Invisible\InvisibleServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app)
    {
        return [
            InvisibleServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Set app key for encryption
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.debug', true);

        // Set up invis config
        $app['config']->set('invis.secret', 'test-secret-key');
        $app['config']->set('invis.threshold', 0.5);
        $app['config']->set('invis.honey_field.enabled', true);
        $app['config']->set('invis.honey_field.name', 'website');
        $app['config']->set('invis.dynamic_fields.enabled', true);
        $app['config']->set('invis.turnstile.enabled', false);
    }
}