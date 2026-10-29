<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Facades;

use Illuminate\Support\Facades\Facade;
use M0d1xd\ExpoNotification\PushNotifier as Notifier;

/**
 * @method static \M0d1xd\ExpoNotification\Models\PushNotification send(\M0d1xd\ExpoNotification\ExpoMessage $message, mixed $recipients)
 *
 * @see Notifier
 */
final class PushNotifier extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Notifier::class;
    }
}
