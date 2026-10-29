<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use M0d1xd\ExpoNotification\Tests\TestCase;

final class SendTestNotificationCommandTest extends TestCase
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
}
