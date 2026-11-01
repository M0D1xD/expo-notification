<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature\Filament;

use Filament\Tables\Grouping\Group;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages\ListPushTokens;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages\ViewPushToken;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\RelationManagers\DeliveriesRelationManager;
use M0d1xd\ExpoNotification\Filament\Support\OwnerKey;
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

    public function test_the_owner_group_describes_the_owners_devices(): void
    {
        $rana = User::make('Rana');
        $phone = $this->device('rana-phone', $rana);
        $old = $this->device('rana-old', $rana);
        $this->device('rana-ipad', $rana, platform: 'ios');
        $phone->forceFill(['last_registered_at' => now()->subHours(3)])->save();
        $old->forceFill(['last_registered_at' => now()->subDays(5), 'active' => false])->save();
        PushToken::query()->where('device_id', 'rana-ipad')->update(['last_registered_at' => now()->subDays(2)]);

        $description = $this->groupDescription($phone);

        $this->assertStringContainsString('3 devices (2 active)', strip_tags($description));
        $this->assertStringContainsString('android ×2', strip_tags($description));
        $this->assertStringContainsString('ios ×1', strip_tags($description));
        $this->assertStringContainsString('last registered 3 hours ago', strip_tags($description));
        $this->assertStringContainsString(
            e(PushTokenResource::getUrl('owner', ['owner' => OwnerKey::encode(OwnerKey::fromDevice($phone))])),
            $description,
        );
    }

    public function test_the_guest_group_has_counts_but_no_analysis_link(): void
    {
        $guest = $this->device('guest-phone');
        $this->device('guest-tablet');

        $description = $this->groupDescription($guest);

        $this->assertStringContainsString('2 devices (2 active)', strip_tags($description));
        $this->assertStringNotContainsString('href', $description);
        $this->assertStringNotContainsString('last registered', $description);
    }

    public function test_the_description_escapes_stored_values(): void
    {
        $owned = $this->device('x', User::make('Rana'));
        $owned->forceFill(['platform' => '<script>x</script>'])->save();

        $description = $this->groupDescription($owned);

        $this->assertStringNotContainsString('<script>', $description);
        $this->assertStringContainsString('&lt;script&gt;', $description);
    }

    public function test_each_owner_group_is_summarised_with_one_query(): void
    {
        foreach (['Rana', 'Sami'] as $name) {
            $owner = User::make($name);
            foreach (range(1, 3) as $i) {
                $this->device("{$name}-{$i}", $owner);
            }
        }

        DB::enableQueryLog();
        Livewire::test(ListPushTokens::class)->set('tableGrouping', 'owner')->assertSee(['3 devices', 'Rana', 'Sami']);
        $summaries = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains(strtolower($q['query']), 'max('));

        $this->assertCount(2, $summaries);
    }

    public function test_the_device_page_links_to_its_owners_analysis(): void
    {
        $owned = $this->device('owned', User::make('Rana'));
        $guest = $this->device('guest');

        Livewire::test(ViewPushToken::class, ['record' => $owned->getKey()])
            ->assertActionVisible('ownerAnalysis')
            ->assertActionHasUrl('ownerAnalysis', PushTokenResource::getUrl('owner', ['owner' => OwnerKey::encode(OwnerKey::fromDevice($owned))]));
        Livewire::test(ViewPushToken::class, ['record' => $guest->getKey()])->assertActionHidden('ownerAnalysis');
    }

    private function groupDescription(PushToken $device): string
    {
        $group = Livewire::test(ListPushTokens::class)->set('tableGrouping', 'owner')->instance()->getTableGrouping();
        $device = $device->fresh('owner');

        return (string) $group->getDescription($device, $group->getTitle($device))?->toHtml();
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
