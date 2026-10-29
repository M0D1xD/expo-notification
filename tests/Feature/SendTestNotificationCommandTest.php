<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\Tests\DatabaseTestCase;

final class SendTestNotificationCommandTest extends DatabaseTestCase
{
    public function test_it_sends_and_prints_the_ticket(): void
    {
        Http::fake(['*/send' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]])]);

        $this->artisan('expo-notification:send', ['tokens' => ['ExpoPushToken[a]'], '--title' => 'parity'])
            ->expectsOutputToContain('ticket-1')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request['0']['title'] === 'parity');
    }

    public function test_it_fails_when_a_ticket_is_an_error(): void
    {
        Http::fake(['*/send' => Http::response(['data' => [
            ['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])]);

        $this->artisan('expo-notification:send', ['tokens' => ['ExpoPushToken[a]']])
            ->expectsOutputToContain('DeviceNotRegistered')
            ->assertFailed();
    }

    public function test_it_rejects_an_invalid_token_without_sending(): void
    {
        Http::fake();

        $this->artisan('expo-notification:send', ['tokens' => ['nope']])->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_track_sends_to_registered_devices_and_records_deliveries(): void
    {
        $this->app->make(PushTokenRegistry::class)
            ->register(['device_id' => 'phone', 'push_token' => 'ExpoPushToken[a]', 'platform' => 'android']);
        Http::fake(['*/send' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]])]);

        $this->artisan('expo-notification:send', ['tokens' => ['ExpoPushToken[a]'], '--track' => true])
            ->expectsOutputToContain('ticket-1')
            ->assertSuccessful();

        $this->assertSame('ticket-1', PushNotificationDelivery::query()->sole()->ticket_id);
        Http::assertSent(fn (Request $request) => isset($request[0]['data']['notificationId']));
    }

    public function test_track_rejects_unregistered_tokens(): void
    {
        Http::fake();

        $this->artisan('expo-notification:send', ['tokens' => ['ExpoPushToken[nobody]'], '--track' => true])
            ->expectsOutputToContain('Not registered')
            ->assertExitCode(2);

        Http::assertNothingSent();
    }
}
