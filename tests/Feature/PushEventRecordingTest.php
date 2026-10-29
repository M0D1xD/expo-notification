<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\PushNotifier;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PushEventRecordingTest extends DatabaseTestCase
{
    private const array DEVICE = ['device_id' => 'phone', 'push_token' => 'ExpoPushToken[phone]'];

    private PushNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $token = $this->app->make(PushTokenRegistry::class)->register([...self::DEVICE, 'platform' => 'android']);
        Http::fake(['*/send' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket']]])]);
        $this->notification = $this->app->make(PushNotifier::class)->send(ExpoMessage::create('Hi', 'There'), $token);
    }

    /** @param  array<string, mixed>  $overrides */
    private function event(array $overrides = []): array
    {
        return ['notification_id' => $this->notification->id, 'type' => 'received', 'occurred_at' => now()->toIso8601String(), ...$overrides];
    }

    public function test_it_stores_events_and_reports_the_counts(): void
    {
        $this->postJson('expo-notification/push-events', [...self::DEVICE, 'events' => [
            $this->event(['source' => 'task', 'app_state' => 'background']),
            $this->event(['type' => 'opened', 'source' => 'cold-start']),
            $this->event(),
            $this->event(['notification_id' => 'unknown']),
        ]])->assertOk()->assertExactJson(['stored' => 2, 'duplicates' => 1, 'ignored' => 1]);

        $this->assertNotNull($this->notification->deliveries()->sole()->opened_at);
    }

    public function test_it_requires_the_stored_pair(): void
    {
        $this->postJson('expo-notification/push-events', [...self::DEVICE, 'push_token' => 'ExpoPushToken[other]', 'events' => [$this->event()]])
            ->assertNotFound();
    }

    /** @return iterable<string, array{\Closure, string}> */
    public static function invalidBatches(): iterable
    {
        yield 'no events' => [fn () => [], 'events'];
        yield 'too many events' => [fn (self $t) => array_fill(0, 101, $t->event()), 'events'];
        yield 'unknown type' => [fn (self $t) => [$t->event(['type' => 'clicked'])], 'events.0.type'];
        yield 'missing time' => [fn (self $t) => [$t->event(['occurred_at' => null])], 'events.0.occurred_at'];
        yield 'missing notification' => [fn (self $t) => [$t->event(['notification_id' => null])], 'events.0.notification_id'];
    }

    #[DataProvider('invalidBatches')]
    public function test_it_validates_the_batch(\Closure $events, string $field): void
    {
        $this->postJson('expo-notification/push-events', [...self::DEVICE, 'events' => $events($this)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_the_route_is_throttled(): void
    {
        $this->assertContains('throttle:60,1', Route::getRoutes()->getByName('expo-notification.push-events.store')->gatherMiddleware());
    }
}
