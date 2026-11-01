<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Support;

/** Where a deliveries table is shown, which decides its columns. */
enum DeliveryTableContext
{
    /** One notification: a row per device. */
    case Notification;

    /** One device: a row per notification. */
    case Device;

    /** One owner: a row per notification per device. */
    case Owner;

    public function showsDevice(): bool
    {
        return $this !== self::Device;
    }

    public function showsNotification(): bool
    {
        return $this !== self::Notification;
    }

    public function showsOwner(): bool
    {
        return $this === self::Notification;
    }
}
