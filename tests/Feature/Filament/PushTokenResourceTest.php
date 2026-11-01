<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature\Filament;

use Filament\Tables\Grouping\Group;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages\ListPushTokens;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages\ViewPushToken;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\RelationManagers\DeliveriesRelationManager;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\Tests\Fixtures\User;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class PushTokenResourceTest extends FilamentTestCase
{
    public function test_the_list_shows_owners_and_filters_guests(): void
    {
        $owned = $this->device('owned', User::make('Rana'));
        $guest = $this->device('guest');

        Livewire::test(ListPushTokens::class)
            ->assertCanSeeTableRecords([$owned, $guest])
            ->assertSee(['Rana', 'Guest'])
            ->filterTable('owner', false)
            ->assertCanSeeTableRecords([$guest])
            ->assertCanNotSeeTableRecords([$owned]);
    }

    public function test_the_list_can_be_grouped_by_owner(): void
    {
        $rana = User::make('Rana');
        $sami = User::make('Sami');
        $ranaPhone = $this->device('rana-phone', $rana);
        $samiPhone = $this->device('sami-phone', $sami);
        $ranaTablet = $this->device('rana-tablet', $rana);
        $guest = $this->device('guest-phone');

        $component = Livewire::test(ListPushTokens::class)
            ->set('tableGrouping', 'owner')
            ->assertCanSeeTableRecords([$ranaPhone, $ranaTablet, $samiPhone, $guest]);

        $this->assertSame(['Guest', 'Rana', 'Rana', 'Sami'], array_values($this->groupTitles($component)));
        $this->assertEqualsCanonicalizing([$ranaPhone->id, $ranaTablet->id], array_keys($this->groupTitles($component), 'Rana', true));
    }

    public function test_owners_with_the_same_name_get_their_own_group(): void
    {
        $first = $this->device('first', User::make('Sam'));
        $second = $this->device('second', User::query()->create(['name' => 'Sam', 'email' => 'sam2@example.test', 'password' => 'secret']));

        $group = Livewire::test(ListPushTokens::class)->set('tableGrouping', 'owner')->instance()->getTableGrouping();

        $this->assertNotSame($group->getStringKey($first->fresh('owner')), $group->getStringKey($second->fresh('owner')));
        $this->assertSame([$first->id], $this->scopedIds($group, $group->getStringKey($first->fresh('owner'))));
    }

    public function test_guests_form_one_group_in_morph_mode(): void
    {
        $guest = $this->device('guest-phone');
        $this->device('owned', User::make('Rana'));

        $group = Livewire::test(ListPushTokens::class)->set('tableGrouping', 'owner')->instance()->getTableGrouping();

        $this->assertNull($group->getStringKey($guest->fresh('owner')));
        $this->assertSame([$guest->id], $this->scopedIds($group, null));
        $this->assertSame([], $this->scopedIds($group, 'malformed-key'));
    }

    #[DefineEnvironment('usesForeignOwner')]
    public function test_the_list_can_be_grouped_by_owner_in_foreign_mode(): void
    {
        $rana = User::make('Rana');
        $ranaPhone = $this->device('rana-phone', $rana);
        $guest = $this->device('guest-phone');
        $ranaTablet = $this->device('rana-tablet', $rana);

        $component = Livewire::test(ListPushTokens::class)
            ->set('tableGrouping', 'owner')
            ->assertCanSeeTableRecords([$ranaPhone, $ranaTablet, $guest]);
        $group = $component->instance()->getTableGrouping();

        $this->assertSame(['Guest', 'Rana', 'Rana'], array_values($this->groupTitles($component)));
        $this->assertEqualsCanonicalizing([$ranaPhone->id, $ranaTablet->id], $this->scopedIds($group, $group->getStringKey($ranaPhone->fresh('owner'))));
        $this->assertSame([$guest->id], $this->scopedIds($group, $group->getStringKey($guest->fresh('owner'))));
    }

    public function test_the_list_is_not_grouped_by_default(): void
    {
        $this->assertNull(Livewire::test(ListPushTokens::class)->instance()->getTableGrouping());
    }

    /** @return array<int, string> group title of each listed record, keyed by id, in table order */
    private function groupTitles(Testable $component): array
    {
        $table = $component->instance();
        $group = $table->getTableGrouping();

        return $table->getTableRecords()
            ->mapWithKeys(fn (PushToken $record) => [$record->getKey() => (string) $group->getTitle($record)])
            ->all();
    }

    /** @return list<int> ids of the devices in one group, as collapsing and group summaries scope them */
    private function scopedIds(Group $group, ?string $key): array
    {
        return $group->scopeQueryByKey(PushToken::query(), $key)->pluck('id')->all();
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
