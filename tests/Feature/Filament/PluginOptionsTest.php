<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature\Filament;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Filesystem\Filesystem;
use Livewire\Livewire;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Pages\ListPushNotifications;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\PushNotificationResource;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages\ListPushTokens;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;
use M0d1xd\ExpoNotification\Filament\Widgets\PushActivityChart;
use M0d1xd\ExpoNotification\Filament\Widgets\PushStatsOverview;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Tests\Fixtures\User;

/** Each test registers the plugin with the options named after it (see plugin()). */
class PluginOptionsTest extends FilamentTestCase
{
    private ?string $langPath = null;

    /** A published override (lang/vendor/expo-notification) changes only the keys it has. */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        if ($this->name() === 'test_the_labels_come_from_the_translations') {
            $this->langPath = sys_get_temp_dir().'/expo-notification-lang-'.uniqid();
            (new Filesystem)->ensureDirectoryExists($this->langPath.'/vendor/expo-notification/en');
            file_put_contents($this->langPath.'/vendor/expo-notification/en/filament.php', '<?php return '.var_export([
                'navigation' => ['group' => 'Mitteilungen'],
                'devices' => ['navigation_label' => 'Geräte'],
            ], true).';');
            $app->useLangPath($this->langPath);
        }
    }

    protected function tearDown(): void
    {
        if ($this->langPath !== null) {
            (new Filesystem)->deleteDirectory($this->langPath);
        }

        parent::tearDown();
    }

    protected function plugin(): ExpoNotificationPlugin
    {
        $plugin = parent::plugin();

        return match ($this->name()) {
            'test_the_section_and_items_can_be_renamed' => $plugin
                ->navigationGroup('Notifications Center')
                ->notificationsResource(label: 'Sent')
                ->devicesResource(label: 'Phones'),
            'test_the_items_can_be_sorted' => $plugin->navigationSort(5),
            'test_a_resource_can_be_turned_off' => $plugin->devicesResource(enabled: false),
            'test_the_dashboard_widgets_can_be_turned_off' => $plugin->widgets([]),
            'test_sending_can_be_turned_off' => $plugin->sending(false),
            'test_chosen_users_needs_an_owner_model' => $plugin->ownerModel(null),
            default => $plugin,
        };
    }

    /** @return array<string, list<string>> group label => item labels, as the sidebar shows them */
    private function navigation(): array
    {
        $this->get('/admin')->assertOk();

        return collect(Filament::getNavigation())
            ->mapWithKeys(fn (NavigationGroup $group) => [
                (string) $group->getLabel() => collect($group->getItems())->map(fn (NavigationItem $item) => $item->getLabel())->values()->all(),
            ])
            ->except('')
            ->all();
    }

    public function test_the_default_section_is_push_notifications(): void
    {
        $this->assertSame(['Push notifications' => ['Notifications', 'Devices']], $this->navigation());
        $this->assertContains(PushStatsOverview::class, Filament::getWidgets());
        $this->assertContains(PushActivityChart::class, Filament::getWidgets());
    }

    public function test_the_section_and_items_can_be_renamed(): void
    {
        $this->assertSame(['Notifications Center' => ['Sent', 'Phones']], $this->navigation());
        $this->get(PushTokenResource::getUrl('index'))->assertSee('<h1', false)->assertSeeText('Phones');
        $this->assertSame('Phones', PushTokenResource::getPluralModelLabel());
    }

    public function test_the_labels_come_from_the_translations(): void
    {
        $this->assertSame(['Mitteilungen' => ['Notifications', 'Geräte']], $this->navigation());
    }

    public function test_the_items_can_be_sorted(): void
    {
        $this->assertSame(5, PushNotificationResource::getNavigationSort());
        $this->assertSame(6, PushTokenResource::getNavigationSort());
    }

    public function test_a_resource_can_be_turned_off(): void
    {
        $this->get('/admin/push-notifications')->assertOk();
        $this->get('/admin/push-tokens')->assertNotFound();
        $this->assertSame(['Push notifications' => ['Notifications']], $this->navigation());
    }

    public function test_the_dashboard_widgets_can_be_turned_off(): void
    {
        $this->get('/admin')->assertOk();

        $this->assertNotContains(PushStatsOverview::class, Filament::getWidgets());
        $this->assertNotContains(PushActivityChart::class, Filament::getWidgets());
    }

    public function test_sending_can_be_turned_off(): void
    {
        $device = $this->device('phone');

        Livewire::test(ListPushNotifications::class)->assertActionHidden('sendNotification');
        Livewire::test(ListPushTokens::class)
            ->assertActionHidden('sendNotification')
            ->assertTableActionHidden('sendTestNotification', $device)
            ->assertTableActionVisible('deactivate', $device);
    }

    public function test_chosen_users_needs_an_owner_model(): void
    {
        $this->device('phone', User::make('Rana'));

        Livewire::test(ListPushNotifications::class)
            ->callAction('sendNotification', data: ['title' => 'Hi', 'body' => 'You', 'target' => 'users', 'users' => [1]])
            ->assertHasActionErrors(['target']);

        $this->assertSame(0, PushNotification::query()->count());
    }
}
