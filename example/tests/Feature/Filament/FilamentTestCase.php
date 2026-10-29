<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use M0d1xd\ExpoNotification\EventRecorder;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\Facades\PushNotifier;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use Tests\TestCase;

/**
 * Builds data through the SDK, the way the phones do: register, send (Expo faked), then device events.
 */
abstract class FilamentTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    /** @var list<string> push tokens Expo rejects with DeviceNotRegistered */
    protected array $rejected = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');
        $this->fakeExpo();
    }

    /** Every message gets an ok ticket, except to the tokens in $rejected. */
    private function fakeExpo(): void
    {
        Http::fake(['*/send' => function (Request $request) {
            $body = $request->hasHeader('Content-Encoding', 'gzip') ? gzdecode($request->body()) : $request->body();

            return Http::response(['data' => array_map(
                fn (array $message) => in_array($message['to'], $this->rejected, true)
                    ? ['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']]
                    : ['status' => 'ok', 'id' => 'ticket-'.$message['to']],
                json_decode($body, true),
            )]);
        }]);
    }

    protected function device(string $name, ?User $owner = null, string $platform = 'android'): PushToken
    {
        return app(PushTokenRegistry::class)->register(
            ['device_id' => $name, 'push_token' => "ExponentPushToken[{$name}]", 'platform' => $platform],
            $owner,
        );
    }

    protected function send(mixed $recipients, string $title = 'Hello'): PushNotification
    {
        return PushNotifier::send(ExpoMessage::create($title, 'World'), $recipients);
    }

    protected function report(PushToken $device, PushNotification $notification, string $type): void
    {
        app(EventRecorder::class)->record($device->fresh(), [
            ['notification_id' => $notification->id, 'type' => $type, 'source' => 'listener', 'occurred_at' => now()->toIso8601String()],
        ]);
    }

    /**
     * Four devices: a opened, b received, c silent, d rejected by Expo.
     *
     * @return array{PushNotification, Collection<string, PushToken>}
     */
    protected function sendMixed(): array
    {
        $devices = collect(['a', 'b', 'c', 'd'])->mapWithKeys(fn (string $name) => [$name => $this->device($name)]);
        $this->rejected = ['ExponentPushToken[d]'];
        $notification = $this->send($devices->values());
        $this->report($devices['a'], $notification, 'opened');
        $this->report($devices['b'], $notification, 'received');

        return [$notification, $devices];
    }
}
