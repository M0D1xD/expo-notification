<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature\Filament;

use Livewire\Livewire;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages\OwnerAnalysis;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Widgets\OwnerStats;
use M0d1xd\ExpoNotification\Filament\Support\OwnerKey;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\Tests\Fixtures\User;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/** Rana has a phone (received the notification) and a tablet (didn't); Sami and a guest got it too. */
class OwnerAnalysisTest extends FilamentTestCase
{
    private PushToken $phone;

    private PushToken $tablet;

    private string $owner;

    private function seedOwners(): void
    {
        $rana = User::make('Rana');
        $this->phone = $this->device('rana-phone', $rana);
        $this->tablet = $this->device('rana-tablet', $rana, platform: 'ios');
        $sami = $this->device('sami-phone', User::make('Sami'));
        $guest = $this->device('guest-phone');

        $notification = $this->send(collect([$this->phone, $this->tablet, $sami, $guest]), 'Big news');
        $this->report($this->phone, $notification, 'received');
        $this->report($sami, $notification, 'received');

        $this->owner = OwnerKey::encode(OwnerKey::fromDevice($this->phone));
    }

    /** @return list<PushNotificationDelivery> */
    private function deliveriesOf(PushToken ...$devices): array
    {
        return PushNotificationDelivery::query()->whereIn('push_token_id', array_map(fn (PushToken $d) => $d->id, $devices))->get()->all();
    }

    public function test_the_page_renders_for_the_owner(): void
    {
        $this->seedOwners();

        $this->get(PushTokenResource::getUrl('owner', ['owner' => $this->owner]))
            ->assertOk()
            ->assertSee(['Rana', 'rana-phone', 'rana-tablet', 'Big news']);
    }

    public function test_it_lists_only_the_owners_deliveries_across_their_devices(): void
    {
        $this->seedOwners();
        $others = PushNotificationDelivery::query()->whereNotIn('push_token_id', [$this->phone->id, $this->tablet->id])->get();

        Livewire::test(OwnerAnalysis::class, ['owner' => $this->owner])
            ->assertCanSeeTableRecords($this->deliveriesOf($this->phone, $this->tablet))
            ->assertCanNotSeeTableRecords($others)
            ->assertCountTableRecords(2);
    }

    public function test_the_delivered_filter_separates_received_from_not_received(): void
    {
        $this->seedOwners();

        Livewire::test(OwnerAnalysis::class, ['owner' => $this->owner])
            ->filterTable('delivered', true)
            ->assertCanSeeTableRecords($this->deliveriesOf($this->phone))
            ->assertCanNotSeeTableRecords($this->deliveriesOf($this->tablet))
            ->filterTable('delivered', false)
            ->assertCanSeeTableRecords($this->deliveriesOf($this->tablet))
            ->assertCanNotSeeTableRecords($this->deliveriesOf($this->phone));
    }

    public function test_the_stats_cover_all_of_the_owners_devices(): void
    {
        $this->seedOwners();

        // 2 targeted, 1 delivered (50%): Sami's and the guest's deliveries are not counted.
        Livewire::test(OwnerStats::class, ['ownerKey' => OwnerKey::fromDevice($this->phone)])
            ->assertSeeText('50%');
    }

    public function test_an_unknown_or_malformed_owner_is_not_found(): void
    {
        $this->seedOwners();

        $this->get(PushTokenResource::getUrl('owner', ['owner' => 'not-base64!']))->assertNotFound();
        $this->get(PushTokenResource::getUrl('owner', ['owner' => OwnerKey::encode(User::class.':999')]))->assertNotFound();
        $this->get(PushTokenResource::getUrl('owner', ['owner' => OwnerKey::encode('no-separator')]))->assertNotFound();
    }

    #[DefineEnvironment('usesForeignOwner')]
    public function test_it_works_in_foreign_mode(): void
    {
        $this->seedOwners();

        $this->assertSame((string) $this->phone->user_id, OwnerKey::fromDevice($this->phone));

        Livewire::test(OwnerAnalysis::class, ['owner' => $this->owner])
            ->assertCanSeeTableRecords($this->deliveriesOf($this->phone, $this->tablet))
            ->assertCountTableRecords(2);
    }

    public function test_notifications_with_the_same_title_get_their_own_group(): void
    {
        $phone = $this->device('phone', User::make('Rana'));
        $this->send($phone, 'Daily digest');
        $this->travel(1)->minutes();
        $this->send($phone, 'Daily digest');

        $titles = PushNotificationDelivery::query()->with('notification')->get()
            ->map(fn (PushNotificationDelivery $d) => OwnerAnalysis::notificationGroupTitle($d));

        $this->assertCount(2, $titles->unique());
        $this->assertStringStartsWith('Daily digest · ', $titles->first());
    }

    public function test_keys_round_trip_through_the_url_even_with_colons_and_backslashes(): void
    {
        $key = 'App\\Models\\Tenant:User:42';

        $this->assertSame($key, OwnerKey::decode(OwnerKey::encode($key)));
        $this->assertSame(
            'select * from "push_tokens" where "push_tokens"."owner_type" = ? and "push_tokens"."owner_id" = ?',
            OwnerKey::scope(PushToken::query(), $key)->toSql(),
        );
        $this->assertSame(['App\\Models\\Tenant:User', '42'], OwnerKey::scope(PushToken::query(), $key)->getBindings());
    }
}
