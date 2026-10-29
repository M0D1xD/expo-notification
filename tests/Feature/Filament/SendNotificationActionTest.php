<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature\Filament;

use Livewire\Livewire;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Pages\ListPushNotifications;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\PushNotificationResource;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\Tests\Fixtures\User;

class SendNotificationActionTest extends FilamentTestCase
{
    public function test_it_sends_to_all_active_devices_and_opens_the_notification(): void
    {
        $this->device('a');
        $this->device('b', platform: 'ios');
        $this->app->make(PushTokenRegistry::class)->deactivate($this->device('inactive'));

        $page = Livewire::test(ListPushNotifications::class)
            ->callAction('sendNotification', data: ['title' => 'Hi', 'body' => 'All', 'url' => '/details', 'target' => 'all'])
            ->assertHasNoActionErrors();

        $notification = PushNotification::query()->sole();
        $page->assertRedirect(PushNotificationResource::getUrl('view', ['record' => $notification]));
        $this->assertSame(2, $notification->deliveries()->count());
        $this->assertSame('/details', $notification->data['url']);
    }

    public function test_it_sends_to_chosen_users(): void
    {
        $rana = User::make('Rana');
        $this->device('rana-phone', $rana);
        $this->device('rana-tablet', $rana);
        $this->device('someone-else', User::make('Omar'));

        Livewire::test(ListPushNotifications::class)
            ->callAction('sendNotification', data: ['title' => 'Hi', 'body' => 'You', 'target' => 'users', 'users' => [$rana->id]])
            ->assertHasNoActionErrors();

        $this->assertEqualsCanonicalizing(
            $rana->pushTokens()->pluck('id')->all(),
            PushNotification::query()->sole()->deliveries()->pluck('push_token_id')->all(),
        );
    }

    public function test_chosen_users_need_at_least_one_user(): void
    {
        Livewire::test(ListPushNotifications::class)
            ->callAction('sendNotification', data: ['title' => 'Hi', 'body' => 'You', 'target' => 'users', 'users' => []])
            ->assertHasActionErrors(['users' => 'required']);

        $this->assertSame(0, PushNotification::query()->count());
    }
}
