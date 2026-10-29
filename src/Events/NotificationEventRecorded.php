<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Events;

use M0d1xd\ExpoNotification\Models\PushNotificationEvent;

/** A device reported a new received, opened or dismissed event. */
final readonly class NotificationEventRecorded
{
    public function __construct(public PushNotificationEvent $event) {}
}
