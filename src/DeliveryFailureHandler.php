<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use Illuminate\Contracts\Events\Dispatcher;
use M0d1xd\ExpoNotification\Events\DeliveryFailed;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;

/** Shared by sending and receipt checks: deactivate dead tokens, then dispatch {@see DeliveryFailed}. */
final class DeliveryFailureHandler
{
    public function __construct(private readonly PushTokenRegistry $registry, private readonly Dispatcher $events) {}

    public function handle(PushNotificationDelivery $delivery): void
    {
        $token = $delivery->pushToken;

        // Only the token that failed: the device may have registered a new one since.
        if ($delivery->error_type === ExpoErrorType::DeviceNotRegistered->value && $token?->push_token === $delivery->push_token) {
            $this->registry->deactivate($token);
        }

        $this->events->dispatch(new DeliveryFailed($delivery));
    }
}
