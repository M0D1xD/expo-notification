<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use M0d1xd\ExpoNotification\Events\DeliveryFailed;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\Facades\PushNotifier;
use M0d1xd\ExpoNotification\LocalizedMessage;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\Tests\DatabaseTestCase;
use M0d1xd\ExpoNotification\Tests\Fixtures\LocalizedUser;
use M0d1xd\ExpoNotification\Tests\Fixtures\User;
use Orchestra\Testbench\Attributes\DefineEnvironment;

final class PushNotifierTest extends DatabaseTestCase
{
    private function device(string $name, ?Model $owner = null, string $platform = 'android', ?string $locale = null): PushToken
    {
        return $this->app->make(PushTokenRegistry::class)
            ->register(['device_id' => $name, 'push_token' => "ExpoPushToken[{$name}]", 'platform' => $platform, 'locale' => $locale], $owner);
    }

    /**
     * The title each device was sent, keyed by token name.
     *
     * @return array<string, string|null>
     */
    private static function sentTitles(): array
    {
        return Http::recorded()
            ->flatMap(fn (array $pair) => self::messages($pair[0]))
            ->mapWithKeys(fn (array $message) => [trim(strstr($message['to'], '['), '[]') => $message['title'] ?? $message['data']['render']['title'] ?? null])
            ->sortKeys()
            ->all();
    }

    /**
     * The messages in a send request; bodies over the gzip threshold arrive compressed.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function messages(Request $request): array
    {
        $body = $request->hasHeader('Content-Encoding', 'gzip') ? gzdecode($request->body()) : $request->body();

        return json_decode($body, true);
    }

    /** Answers every send request with one ok ticket per message, ids `ticket-{token name}`. */
    private function fakeOkTickets(): void
    {
        Http::fake(['*/send' => fn (Request $request) => Http::response(['data' => array_map(
            fn (array $message) => ['status' => 'ok', 'id' => 'ticket-'.trim(strstr($message['to'], '['), '[]')],
            self::messages($request),
        )])]);
    }

    public function test_it_records_the_notification_and_one_delivery_per_device(): void
    {
        $this->fakeOkTickets();
        $user = User::make();
        $this->device('phone', $user);
        $this->device('tablet', $user);

        $notification = PushNotifier::send(ExpoMessage::create('Hi', 'There')->data(['url' => '/chats/1']), $user);

        $this->assertSame(['url' => '/chats/1', 'notificationId' => $notification->id], $notification->fresh()->data);
        $this->assertSame('Hi', $notification->title);
        $this->assertNotNull($notification->sent_at);
        $this->assertEqualsCanonicalizing(
            [['ExpoPushToken[phone]', 'ok', 'ticket-phone'], ['ExpoPushToken[tablet]', 'ok', 'ticket-tablet']],
            $notification->deliveries()->get()->map(fn ($d) => [$d->push_token, $d->ticket_status, $d->ticket_id])->all(),
        );
        Http::assertSent(fn (Request $request) => $request[0]['data']['notificationId'] === $notification->id
            && $request[0]['data']['url'] === '/chats/1');
    }

    public function test_the_message_itself_is_not_changed(): void
    {
        $this->fakeOkTickets();
        $message = ExpoMessage::create('Hi', 'There')->data(['a' => 1]);

        PushNotifier::send($message, $this->device('phone'));

        $this->assertSame(['a' => 1], (array) $message->toArray()['data']);
    }

    public function test_it_skips_inactive_and_duplicate_devices_and_accepts_mixed_recipients(): void
    {
        $this->fakeOkTickets();
        $user = User::make();
        $phone = $this->device('phone', $user);
        $this->app->make(PushTokenRegistry::class)->deactivate($this->device('old', $user));
        $guest = $this->device('guest');

        $notification = PushNotifier::send(ExpoMessage::create('Hi', 'There'), [
            $user, $phone, PushToken::query()->whereKey($guest->id), $user->pushTokens(),
        ]);

        $this->assertEqualsCanonicalizing(['ExpoPushToken[phone]', 'ExpoPushToken[guest]'], $notification->deliveries()->pluck('push_token')->all());
    }

    public function test_an_error_ticket_is_stored_and_a_dead_token_is_deactivated(): void
    {
        Event::fake([DeliveryFailed::class]);
        Http::fake(['*/send' => Http::response(['data' => [
            ['status' => 'error', 'message' => 'not registered', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])]);
        $token = $this->device('phone');

        $notification = PushNotifier::send(ExpoMessage::create('Hi', 'There'), $token);

        $delivery = $notification->deliveries()->sole();
        $this->assertSame(['error', 'DeviceNotRegistered', 'not registered'], [$delivery->ticket_status, $delivery->error_type, $delivery->error_message]);
        $this->assertNull($delivery->ticket_id);
        $this->assertFalse($token->fresh()->active);
        Event::assertDispatched(DeliveryFailed::class, fn (DeliveryFailed $e) => $e->delivery->is($delivery));
    }

    public function test_other_errors_do_not_deactivate_the_token(): void
    {
        Http::fake(['*/send' => Http::response(['data' => [
            ['status' => 'error', 'message' => 'slow down', 'details' => ['error' => 'MessageRateExceeded']],
        ]])]);
        $token = $this->device('phone');

        PushNotifier::send(ExpoMessage::create('Hi', 'There'), $token);

        $this->assertTrue($token->fresh()->active);
    }

    public function test_a_failed_request_marks_only_its_chunk_failed(): void
    {
        Sleep::fake();
        Event::fake([DeliveryFailed::class]);
        $tokens = collect(range(1, 150))->map(fn (int $i) => $this->device("d{$i}"));
        $ok = fn (Request $request) => Http::response(['data' => array_map(fn () => ['status' => 'ok', 'id' => 'ticket'], self::messages($request))]);
        $requests = 0;
        Http::fake(['*/send' => function (Request $request) use (&$requests, $ok) {
            return ++$requests === 1 ? $ok($request) : Http::response('down', 500);
        }]);

        $notification = PushNotifier::send(ExpoMessage::create('Hi', 'There'), $tokens);

        $statuses = $notification->deliveries()->get()->countBy('ticket_status')->all();
        $this->assertSame(['ok' => 100, 'failed' => 50], $statuses);
        $this->assertStringContainsString('HTTP 500', PushNotificationDelivery::query()->where('ticket_status', 'failed')->value('error_message'));
        Event::assertDispatchedTimes(DeliveryFailed::class, 50);
        $this->assertTrue(PushToken::query()->where('active', false)->doesntExist());
    }

    public function test_no_recipients_records_an_empty_notification_without_calling_expo(): void
    {
        Http::fake();

        $notification = PushNotifier::send(ExpoMessage::create('Hi', 'There'), User::make());

        $this->assertSame(0, $notification->deliveries()->count());
        $this->assertSame(1, PushNotification::query()->count());
        Http::assertNothingSent();
    }

    public function test_it_rejects_unknown_recipients(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PushNotifier::send(ExpoMessage::create('Hi', 'There'), 'ExpoPushToken[raw]');
    }

    public function test_tracking_sends_android_a_data_only_push_and_ios_a_content_available_one(): void
    {
        $this->fakeOkTickets();
        $this->device('pixel');
        $this->device('iphone', platform: 'ios');

        $notification = PushNotifier::send(ExpoMessage::create('Hi', 'There')->channelId('chats'), PushToken::query());

        $sent = [];
        Http::assertSent(function (Request $request) use (&$sent) {
            foreach (self::messages($request) as $message) {
                $sent[$message['to']] = $message;
            }

            return true;
        });
        $android = $sent['ExpoPushToken[pixel]'];
        $this->assertArrayNotHasKey('title', $android);
        $this->assertSame('high', $android['priority']);
        $this->assertSame(['title' => 'Hi', 'body' => 'There', 'channelId' => 'chats'], $android['data']['render']);
        $this->assertSame($notification->id, $android['data']['notificationId']);
        $ios = $sent['ExpoPushToken[iphone]'];
        $this->assertSame(['Hi', true], [$ios['title'], $ios['_contentAvailable']]);
        $this->assertArrayNotHasKey('render', $ios['data']);
        $this->assertSame(2, $notification->deliveries()->where('ticket_status', 'ok')->count());
    }

    #[DefineEnvironment('disablesTracking')]
    public function test_without_tracking_every_platform_gets_the_message_as_is(): void
    {
        $this->fakeOkTickets();
        $this->device('pixel');

        PushNotifier::send(ExpoMessage::create('Hi', 'There'), PushToken::query());

        Http::assertSent(fn (Request $request) => self::messages($request)[0]['title'] === 'Hi'
            && ! isset(self::messages($request)[0]['data']['render']));
    }

    protected function disablesTracking($app): void
    {
        $app['config']->set('expo-notification.tracking.enabled', false);
    }

    private static function greeting(): LocalizedMessage
    {
        return LocalizedMessage::make([
            'en' => ExpoMessage::create('Hi', 'Welcome')->data(['url' => '/home']),
            'ar' => ExpoMessage::create('مرحبا', 'أهلا بك')->data(['url' => '/home']),
        ])->fallback('en');
    }

    public function test_a_localized_message_reaches_each_device_in_its_language(): void
    {
        $this->fakeOkTickets();
        $this->device('riyadh', locale: 'ar-SA');
        $this->device('cairo', platform: 'ios', locale: 'ar_EG');
        $this->device('london', locale: 'en-GB');
        $this->device('paris', locale: 'fr-FR');
        $this->device('unknown');

        $notification = PushNotifier::send(self::greeting(), PushToken::query());

        $this->assertSame(
            ['cairo' => 'مرحبا', 'london' => 'Hi', 'paris' => 'Hi', 'riyadh' => 'مرحبا', 'unknown' => 'Hi'],
            self::sentTitles(),
        );
        $this->assertSame(
            ['cairo' => 'ar', 'london' => 'en', 'paris' => 'en', 'riyadh' => 'ar', 'unknown' => 'en'],
            $notification->deliveries()->with('pushToken')->get()->mapWithKeys(fn ($d) => [$d->pushToken->device_id => $d->locale])->sortKeys()->all(),
        );
        Http::assertSentCount(3); // en android, ar android, ar ios
    }

    public function test_it_records_the_fallback_text_and_every_language_sent(): void
    {
        $this->fakeOkTickets();
        $this->device('riyadh', locale: 'ar');
        $this->device('london', locale: 'en');

        $notification = PushNotifier::send(self::greeting(), PushToken::query())->fresh();

        $this->assertSame('Hi', $notification->title);
        $this->assertSame(['url' => '/home', 'notificationId' => $notification->id], $notification->data);
        $this->assertEqualsCanonicalizing(
            ['en' => ['title' => 'Hi', 'body' => 'Welcome'], 'ar' => ['title' => 'مرحبا', 'body' => 'أهلا بك']],
            $notification->translations,
        );
        Http::assertSent(fn (Request $request) => collect(self::messages($request))
            ->every(fn (array $message) => $message['data']['notificationId'] === $notification->id));
    }

    public function test_the_owners_saved_language_wins_over_the_devices(): void
    {
        $this->fakeOkTickets();
        $this->device('phone', LocalizedUser::make('ar', 'Sara'), locale: 'en-US');
        $this->device('tablet', LocalizedUser::make(null, 'Omar'), locale: 'ar');
        $this->device('laptop', LocalizedUser::make('de', 'Hans'), locale: 'ar');

        PushNotifier::send(self::greeting(), PushToken::query());

        $this->assertSame(['laptop' => 'مرحبا', 'phone' => 'مرحبا', 'tablet' => 'مرحبا'], self::sentTitles());
    }

    public function test_a_factory_builds_only_the_languages_that_are_needed(): void
    {
        $this->fakeOkTickets();
        config(['expo-notification.locales.supported' => ['en', 'ar', 'fr']]);
        $this->device('riyadh', locale: 'ar');
        $built = [];

        PushNotifier::send(LocalizedMessage::using(function (string $locale) use (&$built) {
            $built[] = $locale;

            return ExpoMessage::create("title:{$locale}");
        })->fallback('en'), PushToken::query());

        $this->assertSame(['riyadh' => 'title:ar'], self::sentTitles());
        $this->assertEqualsCanonicalizing(['en', 'ar'], $built);
    }

    public function test_a_plain_message_records_no_languages(): void
    {
        $this->fakeOkTickets();
        $this->device('riyadh', locale: 'ar');

        $notification = PushNotifier::send(ExpoMessage::create('Hi'), PushToken::query())->fresh();

        $this->assertNull($notification->translations);
        $this->assertNull($notification->deliveries()->sole()->locale);
    }
}
