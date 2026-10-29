<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PushTokens\Pages\ListPushTokens;
use App\Filament\Resources\PushTokens\Pages\ViewPushToken;
use App\Filament\Resources\PushTokens\RelationManagers\DeliveriesRelationManager;
use App\Models\User;
use Livewire\Livewire;

class PushTokenResourceTest extends FilamentTestCase
{
    public function test_the_list_shows_owners_and_filters_guests(): void
    {
        $owned = $this->device('owned', User::factory()->create(['name' => 'Rana']));
        $guest = $this->device('guest');

        Livewire::test(ListPushTokens::class)
            ->assertCanSeeTableRecords([$owned, $guest])
            ->assertSee(['Rana', 'Guest'])
            ->filterTable('owner', false)
            ->assertCanSeeTableRecords([$guest])
            ->assertCanNotSeeTableRecords([$owned]);
    }

    public function test_deactivate_goes_through_the_registry(): void
    {
        $device = $this->device('phone');

        Livewire::test(ListPushTokens::class)
            ->callTableAction('deactivate', $device)
            ->assertHasNoTableActionErrors();

        $this->assertFalse($device->fresh()->active);
        $this->assertNotNull($device->fresh()->deactivated_at);
    }

    public function test_send_test_notification_targets_only_that_device(): void
    {
        $device = $this->device('phone');
        $this->device('other');

        Livewire::test(ListPushTokens::class)
            ->callTableAction('sendTestNotification', $device, data: ['title' => 'Test', 'body' => 'Ping'])
            ->assertHasNoTableActionErrors();

        $this->assertSame([$device->id], $device->deliveries()->pluck('push_token_id')->all());
        $this->assertSame(1, $device->deliveries()->first()->notification->deliveries()->count());
    }

    public function test_the_view_page_lists_the_devices_history(): void
    {
        $device = $this->device('phone');
        $first = $this->send($device, 'First');
        $second = $this->send($device, 'Second');

        Livewire::test(ViewPushToken::class, ['record' => $device->getKey()])->assertOk();

        Livewire::test(DeliveriesRelationManager::class, ['ownerRecord' => $device, 'pageClass' => ViewPushToken::class])
            ->assertCanSeeTableRecords($device->deliveries)
            ->assertSee([$first->title, $second->title]);
    }
}
