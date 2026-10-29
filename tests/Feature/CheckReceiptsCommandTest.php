<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\PushNotifier;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\Tests\DatabaseTestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;

final class CheckReceiptsCommandTest extends DatabaseTestCase
{
    private function sendOne(): void
    {
        $token = $this->app->make(PushTokenRegistry::class)
            ->register(['device_id' => 'phone', 'push_token' => 'ExpoPushToken[phone]', 'platform' => 'android']);
        Http::fake(['*/send' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]])]);
        $this->app->make(PushNotifier::class)->send(ExpoMessage::create('Hi', 'There'), $token);
    }

    public function test_it_prints_the_counts(): void
    {
        $this->sendOne();
        Http::fake(['*/getReceipts' => Http::response(['data' => ['ticket-1' => ['status' => 'ok']]])]);

        $this->artisan('expo-notification:check-receipts', ['--min-age' => 0])
            ->expectsOutputToContain('Receipts: 1 ok, 0 error, 0 pending, 0 expired.')
            ->assertSuccessful();
    }

    public function test_it_fails_when_expo_is_down(): void
    {
        Sleep::fake();
        $this->sendOne();
        Http::fake(['*/getReceipts' => Http::response('down', 503)]);

        $this->artisan('expo-notification:check-receipts', ['--min-age' => 0])->assertFailed();
    }

    public function test_it_is_scheduled_on_the_configured_cron(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn (ScheduledEvent $e) => str_contains($e->command ?? '', 'expo-notification:check-receipts'));

        $this->assertSame('*/15 * * * *', $event?->expression);
    }

    #[DefineEnvironment('disablesSchedule')]
    public function test_the_schedule_can_be_disabled(): void
    {
        $this->assertFalse(collect($this->app->make(Schedule::class)->events())
            ->contains(fn (ScheduledEvent $e) => str_contains($e->command ?? '', 'expo-notification:check-receipts')));
    }

    protected function disablesSchedule($app): void
    {
        $app['config']->set('expo-notification.receipts.schedule', null);
    }
}
