<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use Illuminate\Database\Eloquent\Builder;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;

/**
 * Delivery and engagement counts for a set of deliveries: one notification, a date range, a device...
 *
 * delivered = the device reported it (not a receipt `ok`); failed = ticket or receipt error.
 */
final readonly class DeliveryStats
{
    public function __construct(
        public int $targeted = 0,
        public int $accepted = 0,
        public int $failed = 0,
        public int $delivered = 0,
        public int $opened = 0,
        public int $dismissed = 0,
    ) {}

    /** @param  Builder<PushNotificationDelivery>  $deliveries */
    public static function for(Builder $deliveries): self
    {
        $count = fn (callable $scope) => $scope(clone $deliveries)->count();

        return new self(
            targeted: (clone $deliveries)->count(),
            accepted: $count(fn (Builder $q) => $q->where('ticket_status', 'ok')),
            failed: $count(fn (Builder $q) => $q->failed()),
            delivered: $count(fn (Builder $q) => $q->delivered()),
            opened: $count(fn (Builder $q) => $q->opened()),
            dismissed: $count(fn (Builder $q) => $q->dismissed()),
        );
    }

    /** From a notification loaded with {@see PushNotification::scopeWithDeliveryStats()}. */
    public static function fromCounts(PushNotification $notification): self
    {
        return new self(
            targeted: (int) $notification->deliveries_count,
            accepted: (int) $notification->accepted_count,
            failed: (int) $notification->failed_count,
            delivered: (int) $notification->delivered_count,
            opened: (int) $notification->opened_count,
            dismissed: (int) $notification->dismissed_count,
        );
    }

    /** Delivered / targeted, 0–1; null when nothing was targeted. */
    public function deliveryRate(): ?float
    {
        return $this->targeted === 0 ? null : $this->delivered / $this->targeted;
    }

    /** Opened / delivered, 0–1; null when nothing was delivered. */
    public function openRate(): ?float
    {
        return $this->delivered === 0 ? null : $this->opened / $this->delivered;
    }
}
