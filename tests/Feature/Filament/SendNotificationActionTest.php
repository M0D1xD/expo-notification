<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature\Filament;

use Livewire\Livewire;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Pages\ListPushNotifications;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\PushNotificationResource;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\Tests\Fixtures\User;

class SendNotificationActionTest extends FilamentTestCase
{
    protected function plugin(): ExpoNotificationPlugin
    {
        return str_contains($this->name(), 'language')
            ? parent::plugin()->locales(['en' => 'English', 'ar' => 'العربية'])
            : parent::plugin();
    }

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

    public function test_with_languages_each_device_gets_its_language(): void
    {
        $this->device('riyadh', locale: 'ar-SA');
        $this->device('london', locale: 'en-GB');

        Livewire::test(ListPushNotifications::class)
            ->callAction('sendNotification', data: [
                'title' => ['en' => 'Hi', 'ar' => 'مرحبا'],
                'body' => ['en' => 'All', 'ar' => 'الكل'],
                'target' => 'all',
            ])
            ->assertHasNoActionErrors();

        $notification = PushNotification::query()->sole();
        $this->assertSame('Hi', $notification->title);
        $this->assertSame(['en' => ['title' => 'Hi', 'body' => 'All'], 'ar' => ['title' => 'مرحبا', 'body' => 'الكل']], $notification->translations);
        $this->assertSame(
            ['london' => 'en', 'riyadh' => 'ar'],
            $notification->deliveries()->with('pushToken')->get()->mapWithKeys(fn ($d) => [$d->pushToken->device_id => $d->locale])->sortKeys()->all(),
        );
        $this->get(PushNotificationResource::getUrl('view', ['record' => $notification]))
            ->assertOk()
            ->assertSee('Languages')
            ->assertSee('مرحبا');
    }

    public function test_with_languages_an_empty_language_gets_the_first_one(): void
    {
        $this->device('riyadh', locale: 'ar');

        Livewire::test(ListPushNotifications::class)
            ->callAction('sendNotification', data: ['title' => ['en' => 'Hi', 'ar' => ''], 'body' => ['en' => 'All', 'ar' => ''], 'target' => 'all'])
            ->assertHasNoActionErrors();

        $notification = PushNotification::query()->sole();
        $this->assertSame(['en' => ['title' => 'Hi', 'body' => 'All']], $notification->translations);
        $this->assertSame('en', $notification->deliveries()->sole()->locale);
    }

    public function test_with_languages_the_first_language_is_required_and_others_need_both_fields(): void
    {
        Livewire::test(ListPushNotifications::class)
            ->callAction('sendNotification', data: ['title' => ['en' => '', 'ar' => 'مرحبا'], 'body' => ['en' => '', 'ar' => ''], 'target' => 'all'])
            ->assertHasActionErrors(['title.en' => 'required', 'body.en' => 'required', 'body.ar' => 'required_with']);

        $this->assertSame(0, PushNotification::query()->count());
    }
}
