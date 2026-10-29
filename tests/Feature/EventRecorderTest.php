<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use M0d1xd\ExpoNotification\EventRecorder;
use M0d1xd\ExpoNotification\Events\NotificationEventRecorded;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushNotificationEvent;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushNotifier;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\Tests\DatabaseTestCase;

final class EventRecorderTest extends DatabaseTestCase
{
    private PushToken $phone;

    private PushNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        $this->phone = $this->device('phone');
        $this->notification = $this->sendTo($this->phone);
    }

    private function device(string $name): PushToken
    {
        return $this->app->make(PushTokenRegistry::class)
            ->register(['device_id' => $name, 'push_token' => "ExpoPushToken[{$name}]", 'platform' => 'android']);
    }

    private function sendTo(PushToken $token): PushNotification
    {
        Http::fake(['*/send' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket']]])]);

        return $this->app->make(PushNotifier::class)->send(ExpoMessage::create('Hi', 'There'), $token);
    }

    /** @param  array<string, mixed>  $overrides */
    private function event(string $type, array $overrides = []): array
    {
        return [
            'notification_id' => $this->notification->id, 'type' => $type, 'source' => 'task',
            'app_state' => 'background', 'occurred_at' => now()->toIso8601String(), ...$overrides,
        ];
    }

    private function record(array ...$events): array
    {
        $result = $this->app->make(EventRecorder::class)->record($this->phone->fresh(), $events);

        return [$result->stored, $result->duplicates, $result->ignored];
    }

    private function delivery(): PushNotificationDelivery
    {
        return $this->notification->deliveries()->sole();
    }

    public function test_received_sets_delivered_at_and_stores_the_event(): void
    {
        Event::fake([NotificationEventRecorded::class]);
        $this->travel(2)->seconds();

        $this->assertSame([1, 0, 0], $this->record($this->event('received')));

        $this->assertTrue($this->delivery()->delivered_at->equalTo(now()));
        $this->assertNull($this->delivery()->opened_at);
        $event = PushNotificationEvent::query()->sole();
        $this->assertSame(['received', 'task', 'background', $this->phone->id], [$event->type, $event->source, $event->app_state, $event->push_token_id]);
        Event::assertDispatched(NotificationEventRecorded::class, fn ($e) => $e->event->is($event));
    }

    public function test_opened_sets_opened_at_and_fills_delivered_at(): void
    {
        $this->travel(5)->seconds();

        $this->record($this->event('opened'));

        $this->assertTrue($this->delivery()->opened_at->equalTo(now()));
        $this->assertTrue($this->delivery()->delivered_at->equalTo(now()));
    }

    public function test_a_later_received_keeps_the_earliest_delivered_at(): void
    {
        $this->travel(5)->seconds();
        $this->record($this->event('opened'));

        $this->record($this->event('received', ['occurred_at' => now()->subSeconds(3)->toIso8601String()]));

        $this->assertTrue($this->delivery()->delivered_at->equalTo(now()->subSeconds(3)));
    }

    public function test_dismissed_sets_dismissed_at(): void
    {
        $this->record($this->event('dismissed'));

        $this->assertNotNull($this->delivery()->dismissed_at);
        $this->assertNotNull($this->delivery()->delivered_at);
    }

    public function test_repeats_are_counted_as_duplicates(): void
    {
        $this->assertSame([1, 1, 0], $this->record($this->event('received'), $this->event('received')));
        $this->assertSame([0, 1, 0], $this->record($this->event('received')));
        $this->assertSame(1, PushNotificationEvent::query()->count());
    }

    public function test_events_for_notifications_not_sent_to_this_device_are_ignored(): void
    {
        $other = $this->sendTo($this->device('tablet'));

        $this->assertSame([0, 0, 2], $this->record(
            $this->event('received', ['notification_id' => $other->id]),
            $this->event('received', ['notification_id' => '01unknownnotification00000']),
        ));
        $this->assertNull($other->deliveries()->sole()->delivered_at);
    }

    public function test_events_still_match_after_the_device_rotated_its_token(): void
    {
        $this->app->make(PushTokenRegistry::class)->register(
            ['device_id' => 'phone', 'push_token' => 'ExpoPushToken[rotated]', 'platform' => 'android', 'previous_push_token' => 'ExpoPushToken[phone]'],
        );

        $this->assertSame([1, 0, 0], $this->record($this->event('received')));
    }

    public function test_occurred_at_is_kept_between_the_send_and_now(): void
    {
        $sentAt = $this->delivery()->sent_at;
        $this->travel(1)->minute();

        $this->record(
            $this->event('received', ['occurred_at' => now()->subYear()->toIso8601String()]),
            $this->event('opened', ['occurred_at' => now()->addYear()->toIso8601String()]),
        );

        $this->assertTrue($this->delivery()->delivered_at->equalTo($sentAt));
        $this->assertTrue($this->delivery()->opened_at->equalTo(now()));
    }
}
