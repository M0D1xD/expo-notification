<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use M0d1xd\ExpoNotification\Events\PushFailed;
use M0d1xd\ExpoNotification\Exceptions\CouldNotGetReceipts;
use M0d1xd\ExpoNotification\Exceptions\CouldNotSendNotification;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\ExpoPushToken;
use M0d1xd\ExpoNotification\Facades\ExpoNotification;
use M0d1xd\ExpoNotification\Tests\TestCase;

final class ExpoClientTest extends TestCase
{
    private const string SEND_URL = 'https://exp.host/--/api/v2/push/send';

    private const string RECEIPTS_URL = 'https://exp.host/--/api/v2/push/getReceipts';

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        Event::fake([PushFailed::class]);
    }

    /** Fake the send endpoint, answering every message with an ok ticket. */
    private function fakeOkSend(): void
    {
        Http::fake([self::SEND_URL => fn (Request $request) => Http::response([
            'data' => array_map(fn ($i) => ['status' => 'ok', 'id' => "ticket-{$i}"], array_keys($this->decode($request))),
        ])]);
    }

    /** @return list<array<string, mixed>> */
    private function decode(Request $request): array
    {
        $body = $request->body();

        return json_decode($request->hasHeader('Content-Encoding', 'gzip') ? gzdecode($body) : $body, true);
    }

    public function test_it_sends_one_message_per_token_and_maps_tickets_back(): void
    {
        $this->fakeOkSend();

        $tickets = ExpoNotification::send(ExpoMessage::create('Hi', 'There')->high(), [
            'ExponentPushToken[a]',
            ExpoPushToken::make('ExponentPushToken[b]'),
        ]);

        $this->assertSame(['ExponentPushToken[a]', 'ExponentPushToken[b]'], $tickets->pluck('token')->all());
        $this->assertSame(['ticket-0', 'ticket-1'], $tickets->pluck('id')->all());
        Http::assertSent(fn (Request $request) => $this->decode($request) === [
            ['to' => 'ExponentPushToken[a]', 'title' => 'Hi', 'body' => 'There', 'priority' => 'high', 'badge' => 0],
            ['to' => 'ExponentPushToken[b]', 'title' => 'Hi', 'body' => 'There', 'priority' => 'high', 'badge' => 0],
        ]);
    }

    public function test_it_drops_duplicate_tokens(): void
    {
        $this->fakeOkSend();

        $tickets = ExpoNotification::send(ExpoMessage::create('a', 'b'), ['ExpoPushToken[a]', 'ExpoPushToken[a]']);

        $this->assertCount(1, $tickets);
    }

    public function test_it_chunks_at_100_messages_per_request(): void
    {
        $this->fakeOkSend();
        $tokens = array_map(fn ($i) => "ExponentPushToken[t{$i}]", range(1, 250));

        $tickets = ExpoNotification::send(ExpoMessage::create('a', 'b'), $tokens);

        $this->assertCount(250, $tickets);
        $this->assertSame($tokens, $tickets->pluck('token')->all());
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => count($this->decode($request)) === 50);
    }

    public function test_it_sends_the_expected_headers_and_no_authorization_without_a_token(): void
    {
        $this->fakeOkSend();

        ExpoNotification::send(ExpoMessage::create('a', 'b'), ['ExpoPushToken[a]']);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('Accept-Encoding', 'gzip, deflate')
            && ! $request->hasHeader('Authorization')
            && ! $request->hasHeader('Content-Encoding'));
    }

    public function test_it_sends_the_access_token_as_a_bearer_token(): void
    {
        config(['expo-notification.access_token' => 'secret']);
        $this->fakeOkSend();

        ExpoNotification::send(ExpoMessage::create('a', 'b'), ['ExpoPushToken[a]']);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer secret'));
    }

    public function test_it_gzips_bodies_above_the_threshold(): void
    {
        $this->fakeOkSend();

        ExpoNotification::send(ExpoMessage::create('a', str_repeat('x', 2000)), ['ExpoPushToken[a]']);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Content-Encoding', 'gzip')
            && $this->decode($request)[0]['body'] === str_repeat('x', 2000));
    }

    public function test_error_tickets_are_returned_and_dispatch_push_failed(): void
    {
        Http::fake([self::SEND_URL => Http::response(['data' => [
            ['status' => 'ok', 'id' => 'ticket-1'],
            ['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])]);

        $tickets = ExpoNotification::send(ExpoMessage::create('a', 'b'), ['ExpoPushToken[ok]', 'ExpoPushToken[gone]']);

        $this->assertTrue($tickets[1]->errorType->isDeviceNotRegistered());
        Event::assertDispatchedTimes(PushFailed::class, 1);
        Event::assertDispatched(PushFailed::class, fn (PushFailed $event) => $event->ticket->token === 'ExpoPushToken[gone]');
    }

    public function test_it_retries_server_errors_and_rate_limits(): void
    {
        Http::fakeSequence(self::SEND_URL)
            ->push('busy', 503)
            ->push('slow down', 429)
            ->push(['data' => [['status' => 'ok', 'id' => 'ticket-1']]]);

        $tickets = ExpoNotification::send(ExpoMessage::create('a', 'b'), ['ExpoPushToken[a]']);

        $this->assertTrue($tickets[0]->isOk());
        Http::assertSentCount(3);
    }

    public function test_it_throws_on_a_client_error_without_retrying(): void
    {
        Http::fake([self::SEND_URL => Http::response(['errors' => [['code' => 'VALIDATION_ERROR']]], 400)]);

        try {
            ExpoNotification::send(ExpoMessage::create('a', 'b'), ['ExpoPushToken[a]']);
            $this->fail('Expected CouldNotSendNotification');
        } catch (CouldNotSendNotification $e) {
            $this->assertSame(400, $e->status);
            $this->assertStringContainsString('VALIDATION_ERROR', $e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_it_throws_when_retries_are_exhausted(): void
    {
        Http::fake([self::SEND_URL => Http::response('down', 500)]);

        $this->expectException(CouldNotSendNotification::class);

        try {
            ExpoNotification::send(ExpoMessage::create('a', 'b'), ['ExpoPushToken[a]']);
        } finally {
            Http::assertSentCount(3);
        }
    }

    public function test_sending_to_no_tokens_makes_no_request(): void
    {
        Http::fake();

        $this->assertCount(0, ExpoNotification::send(ExpoMessage::create('a', 'b'), []));
        Http::assertNothingSent();
    }

    public function test_it_fetches_receipts_in_chunks_of_1000(): void
    {
        Http::fake([self::RECEIPTS_URL => fn (Request $request) => Http::response([
            'data' => collect($this->decodeIds($request))->mapWithKeys(fn ($id) => [$id => ['status' => 'ok']])->all(),
        ])]);
        $ids = array_map(fn ($i) => "ticket-{$i}", range(1, 1500));

        $receipts = ExpoNotification::receipts($ids);

        $this->assertCount(1500, $receipts);
        $this->assertTrue($receipts['ticket-1500']->isOk());
        Http::assertSentCount(2);
    }

    public function test_receipts_map_errors_and_skip_unknown_ids(): void
    {
        Http::fake([self::RECEIPTS_URL => Http::response(['data' => [
            'ticket-1' => ['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])]);

        $receipts = ExpoNotification::receipts(['ticket-1', 'ticket-2']);

        $this->assertSame(['ticket-1'], $receipts->keys()->all());
        $this->assertTrue($receipts['ticket-1']->errorType->isDeviceNotRegistered());
    }

    public function test_receipts_throw_on_failure(): void
    {
        Http::fake([self::RECEIPTS_URL => Http::response('nope', 400)]);

        $this->expectException(CouldNotGetReceipts::class);

        ExpoNotification::receipts(['ticket-1']);
    }

    /** @return list<string> */
    private function decodeIds(Request $request): array
    {
        return $this->decode($request)['ids'];
    }
}
