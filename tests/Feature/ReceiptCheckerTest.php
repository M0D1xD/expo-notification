<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use M0d1xd\ExpoNotification\Events\DeliveryFailed;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushNotifier;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\ReceiptChecker;
use M0d1xd\ExpoNotification\Tests\DatabaseTestCase;

final class ReceiptCheckerTest extends DatabaseTestCase
{
    /** Sends to the named devices; tickets are `ticket-{name}`. */
    private function sendTo(string ...$names): void
    {
        $registry = $this->app->make(PushTokenRegistry::class);
        $tokens = collect($names)->map(fn (string $name) => $registry->register(
            ['device_id' => $name, 'push_token' => "ExpoPushToken[{$name}]", 'platform' => 'android'],
        ));
        Http::fake(['*/send' => Http::response(['data' => array_map(fn (string $name) => ['status' => 'ok', 'id' => "ticket-{$name}"], $names)])]);

        $this->app->make(PushNotifier::class)->send(ExpoMessage::create('Hi', 'There'), $tokens);
    }

    /** @param  array<string, array<string, mixed>>  $receipts */
    private function fakeReceipts(array $receipts): void
    {
        Http::fake(['*/getReceipts' => Http::response(['data' => $receipts])]);
    }

    private function delivery(string $name): PushNotificationDelivery
    {
        return PushNotificationDelivery::query()->where('push_token', "ExpoPushToken[{$name}]")->sole();
    }

    public function test_it_waits_for_the_configured_delay(): void
    {
        $this->sendTo('phone');
        $this->travel(10)->minutes();
        Http::fake();

        $result = $this->app->make(ReceiptChecker::class)->check();

        $this->assertSame(0, $result->ok + $result->pending);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), 'getReceipts'));
    }

    public function test_it_records_ok_and_error_receipts_and_deactivates_dead_tokens(): void
    {
        Event::fake([DeliveryFailed::class]);
        $this->sendTo('phone', 'gone', 'busy');
        $this->travel(16)->minutes();
        $this->fakeReceipts([
            'ticket-phone' => ['status' => 'ok'],
            'ticket-gone' => ['status' => 'error', 'message' => 'not registered', 'details' => ['error' => 'DeviceNotRegistered']],
            'ticket-busy' => ['status' => 'error', 'message' => 'slow down', 'details' => ['error' => 'MessageRateExceeded']],
        ]);

        $result = $this->app->make(ReceiptChecker::class)->check();

        $this->assertSame([1, 2, 0, 0], [$result->ok, $result->errors, $result->pending, $result->expired]);
        $this->assertSame('ok', $this->delivery('phone')->receipt_status);
        $this->assertNull($this->delivery('phone')->delivered_at);
        $this->assertNotNull($this->delivery('phone')->receipt_checked_at);
        $this->assertSame(['error', 'DeviceNotRegistered'], [$this->delivery('gone')->receipt_status, $this->delivery('gone')->error_type]);
        $this->assertSame(['gone'], PushToken::query()->where('active', false)->pluck('device_id')->all());
        Event::assertDispatchedTimes(DeliveryFailed::class, 2);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'getReceipts')
            && $request['ids'] === ['ticket-phone', 'ticket-gone', 'ticket-busy']);
    }

    public function test_a_dead_receipt_does_not_deactivate_a_token_the_device_has_since_replaced(): void
    {
        $this->sendTo('phone');
        $this->app->make(PushTokenRegistry::class)->register(
            ['device_id' => 'phone', 'push_token' => 'ExpoPushToken[new]', 'platform' => 'android', 'previous_push_token' => 'ExpoPushToken[phone]'],
        );
        $this->travel(16)->minutes();
        $this->fakeReceipts(['ticket-phone' => ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]]);

        $this->app->make(ReceiptChecker::class)->check();

        $this->assertTrue(PushToken::query()->sole()->active);
    }

    public function test_missing_receipts_stay_pending_then_expire(): void
    {
        $this->sendTo('phone');
        $this->travel(16)->minutes();
        $this->fakeReceipts([]);

        $this->assertSame(1, $this->app->make(ReceiptChecker::class)->check()->pending);
        $this->assertNull($this->delivery('phone')->receipt_status);

        $this->travel(1)->day();

        $this->assertSame(1, $this->app->make(ReceiptChecker::class)->check()->expired);
        $this->assertSame('expired', $this->delivery('phone')->receipt_status);
        $this->assertSame(0, $this->app->make(ReceiptChecker::class)->check()->expired);
    }

    public function test_min_age_overrides_the_delay(): void
    {
        $this->sendTo('phone');
        $this->fakeReceipts(['ticket-phone' => ['status' => 'ok']]);

        $this->assertSame(1, $this->app->make(ReceiptChecker::class)->check(minAge: 0)->ok);
    }

    public function test_failed_and_error_tickets_are_never_polled(): void
    {
        $this->sendTo('phone');
        PushNotificationDelivery::query()->update(['ticket_status' => 'error']);
        $this->travel(16)->minutes();
        Http::fake();

        $this->app->make(ReceiptChecker::class)->check();

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), 'getReceipts'));
    }
}
