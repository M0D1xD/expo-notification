<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Support\Facades\Http;
use M0d1xd\ExpoNotification\DeliveryStats;
use M0d1xd\ExpoNotification\EventRecorder;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushNotifier;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\Tests\DatabaseTestCase;

final class DeliveryStatsTest extends DatabaseTestCase
{
    private function device(string $name): PushToken
    {
        return $this->app->make(PushTokenRegistry::class)
            ->register(['device_id' => $name, 'push_token' => "ExpoPushToken[{$name}]", 'platform' => 'android']);
    }

    /** Five devices: two opened, one only received, one accepted but silent, one rejected by Expo. */
    private function sendMixed(): PushNotification
    {
        $devices = collect(['a', 'b', 'c', 'd', 'e'])->map(fn (string $name) => $this->device($name));
        Http::fake(['*/send' => Http::response(['data' => [
            ['status' => 'ok', 'id' => 't-a'], ['status' => 'ok', 'id' => 't-b'], ['status' => 'ok', 'id' => 't-c'],
            ['status' => 'ok', 'id' => 't-d'], ['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])]);
        $notification = $this->app->make(PushNotifier::class)->send(ExpoMessage::create('Hi', 'There'), $devices);

        $recorder = $this->app->make(EventRecorder::class);
        foreach (['a' => 'opened', 'b' => 'opened', 'c' => 'received'] as $name => $type) {
            $recorder->record($devices->firstWhere('device_id', $name)->fresh(), [
                ['notification_id' => $notification->id, 'type' => $type, 'occurred_at' => now()->toIso8601String()],
            ]);
        }

        return $notification;
    }

    public function test_it_counts_one_notification(): void
    {
        $stats = $this->sendMixed()->stats();

        $this->assertEquals(new DeliveryStats(targeted: 5, accepted: 4, failed: 1, delivered: 3, opened: 2, dismissed: 0), $stats);
        $this->assertSame(0.6, $stats->deliveryRate());
        $this->assertEqualsWithDelta(2 / 3, $stats->openRate(), 0.0001);
    }

    public function test_the_with_delivery_stats_scope_gives_the_same_numbers_in_one_query(): void
    {
        $notification = $this->sendMixed();

        $loaded = PushNotification::query()->withDeliveryStats()->findOrFail($notification->id);

        $this->assertEquals($notification->stats(), $loaded->stats());
        $this->assertSame(5, $loaded->deliveries_count);
    }

    public function test_it_aggregates_any_delivery_query(): void
    {
        $this->sendMixed();
        $this->sendMixed();

        $stats = DeliveryStats::for(PushNotificationDelivery::query());

        $this->assertSame([10, 6, 4], [$stats->targeted, $stats->delivered, $stats->opened]);
    }

    public function test_a_failed_receipt_counts_as_failed(): void
    {
        $notification = $this->sendMixed();
        $notification->deliveries()->where('ticket_id', 't-d')->update(['receipt_status' => 'error']);

        $this->assertSame(2, $notification->stats()->failed);
    }

    public function test_rates_are_null_without_a_base(): void
    {
        $this->assertNull((new DeliveryStats)->deliveryRate());
        $this->assertNull((new DeliveryStats(targeted: 3))->openRate());
    }
}
