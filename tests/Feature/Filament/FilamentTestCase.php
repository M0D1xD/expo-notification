<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature\Filament;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\QueryBuilder\QueryBuilderServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;
use Livewire\LivewireServiceProvider;
use M0d1xd\ExpoNotification\EventRecorder;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\Facades\PushNotifier;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\Tests\DatabaseTestCase;
use M0d1xd\ExpoNotification\Tests\Fixtures\AdminPanelProvider;
use M0d1xd\ExpoNotification\Tests\Fixtures\User;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

/**
 * Runs the plugin in an "admin" panel and builds data through the SDK, the way the phones do: register, send (Expo
 * faked), then device events.
 */
abstract class FilamentTestCase extends DatabaseTestCase
{
    protected User $admin;

    /** @var list<string> push tokens Expo rejects with DeviceNotRegistered */
    protected array $rejected = [];

    /** Override to register the plugin with other options. */
    protected function plugin(): ExpoNotificationPlugin
    {
        return ExpoNotificationPlugin::make()->ownerModel(User::class);
    }

    protected function setUp(): void
    {
        AdminPanelProvider::$plugin = $this->plugin();

        parent::setUp();

        $this->admin = User::make('Admin');
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');
        $this->fakeExpo();
    }

    protected function tearDown(): void
    {
        AdminPanelProvider::$plugin = null;

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            PowerJoinsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            QueryBuilderServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
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

    protected function device(string $name, ?User $owner = null, string $platform = 'android', ?string $locale = null): PushToken
    {
        return app(PushTokenRegistry::class)->register(
            ['device_id' => $name, 'push_token' => "ExponentPushToken[{$name}]", 'platform' => $platform, 'locale' => $locale],
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
