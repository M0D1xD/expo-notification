<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests;

use M0d1xd\ExpoNotification\Tests\Fixtures\User;

/** Runs the package migrations against a users table; owner-mode config must be set before setUp (DefineEnvironment). */
abstract class DatabaseTestCase extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function usesForeignOwner($app): void
    {
        $app['config']->set('expo-notification.owner', [
            'mode' => 'foreign',
            'key_type' => 'int',
            'model' => User::class,
            'column' => 'user_id',
        ]);
    }
}
