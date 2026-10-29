<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests;

use M0d1xd\ExpoNotification\ExpoNotificationServiceProvider;
use M0d1xd\ExpoNotification\Facades\ExpoNotification;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ExpoNotificationServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['ExpoNotification' => ExpoNotification::class];
    }
}
