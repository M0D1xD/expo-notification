<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use M0d1xd\ExpoNotification\Events\NotificationEventRecorded;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushNotificationEvent;
use M0d1xd\ExpoNotification\Models\PushToken;

/** Stores events a device reports and fills in the delivery timeline. */
final class EventRecorder
{
    public function __construct(private readonly Dispatcher $events) {}

    /**
     * Only events for notifications that were sent to this device are stored; the rest are ignored. Each
     * notification, device and type is stored once. `occurred_at` is kept between the send time and now.
     *
     * Timeline: received sets `delivered_at` (earliest wins); opened and dismissed set their own column and,
     * since the notification must have arrived, `delivered_at` when it is still empty.
     *
     * @param  array<int, array{notification_id: string, type: string, source?: ?string, app_state?: ?string, occurred_at: string}>  $events
     */
    public function record(PushToken $device, array $events): EventRecordResult
    {
        $deliveries = PushNotificationDelivery::query()
            ->where('push_token_id', $device->id)
            ->whereIn('push_notification_id', array_unique(array_column($events, 'notification_id')))
            ->get()
            ->keyBy('push_notification_id');
        $counts = ['stored' => 0, 'duplicates' => 0, 'ignored' => 0];

        foreach ($events as $reported) {
            $delivery = $deliveries->get($reported['notification_id']);

            if ($delivery === null) {
                $counts['ignored']++;

                continue;
            }

            $at = Carbon::parse($reported['occurred_at'])->max($delivery->sent_at ?? now())->min(now());
            $event = PushNotificationEvent::query()->createOrFirst(
                ['push_notification_id' => $delivery->push_notification_id, 'push_token_id' => $device->id, 'type' => $reported['type']],
                ['source' => $reported['source'] ?? null, 'app_state' => $reported['app_state'] ?? null, 'occurred_at' => $at],
            );

            if (! $event->wasRecentlyCreated) {
                $counts['duplicates']++;

                continue;
            }

            $this->updateTimeline($delivery, $reported['type'], $at);
            $this->events->dispatch(new NotificationEventRecorded($event));
            $counts['stored']++;
        }

        return new EventRecordResult(...$counts);
    }

    private function updateTimeline(PushNotificationDelivery $delivery, string $type, Carbon $at): void
    {
        $delivered = $delivery->delivered_at;
        $delivery->delivered_at = $type === 'received' && $delivered !== null ? $delivered->min($at) : ($delivered ?? $at);

        match ($type) {
            'opened' => $delivery->opened_at = $at,
            'dismissed' => $delivery->dismissed_at = $at,
            default => null,
        };

        $delivery->save();
    }
}
