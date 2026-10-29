<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Events;

use M0d1xd\ExpoNotification\PushTicket;

/** Dispatched for every message the Expo Push Service rejected, e.g. `DeviceNotRegistered`. */
final readonly class PushFailed
{
    public function __construct(public PushTicket $ticket) {}
}
