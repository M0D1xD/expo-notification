<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Events;

use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;

/** A tracked delivery failed: an error ticket, a failed request to Expo, or an error receipt. */
final readonly class DeliveryFailed
{
    public function __construct(public PushNotificationDelivery $delivery) {}
}
