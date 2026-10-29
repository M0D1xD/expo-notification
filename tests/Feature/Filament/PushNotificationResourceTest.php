<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature\Filament;

use Livewire\Livewire;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Pages\ListPushNotifications;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Pages\ViewPushNotification;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\RelationManagers\DeliveriesRelationManager;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\RelationManagers\EventsRelationManager;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Widgets\NotificationStats;

class PushNotificationResourceTest extends FilamentTestCase
{
    public function test_the_list_shows_counts_and_rates(): void
    {
        [$notification] = $this->sendMixed();

        Livewire::test(ListPushNotifications::class)
            ->assertCanSeeTableRecords([$notification])
            ->assertTableColumnStateSet('deliveries_count', 4, $notification)
            ->assertTableColumnStateSet('delivered_count', 2, $notification)
            ->assertTableColumnStateSet('opened_count', 1, $notification)
            ->assertTableColumnStateSet('failed_count', 1, $notification)
            ->assertSee('50%');
    }

    public function test_the_has_failures_filter(): void
    {
        [$failed] = $this->sendMixed();
        $this->rejected = [];
        $clean = $this->send($this->device('e'));

        Livewire::test(ListPushNotifications::class)
            ->filterTable('has_failures')
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$clean]);
    }

    public function test_the_view_page_shows_each_device_and_the_events(): void
    {
        [$notification] = $this->sendMixed();

        Livewire::test(ViewPushNotification::class, ['record' => $notification->getKey()])
            ->assertOk()
            ->assertSee('Hello');

        Livewire::test(DeliveriesRelationManager::class, ['ownerRecord' => $notification, 'pageClass' => ViewPushNotification::class])
            ->assertCanSeeTableRecords($notification->deliveries)
            ->assertSee(['DeviceNotRegistered', 'Guest'])
            ->filterTable('opened', true)
            ->assertCountTableRecords(1);

        Livewire::test(EventsRelationManager::class, ['ownerRecord' => $notification, 'pageClass' => ViewPushNotification::class])
            ->assertCanSeeTableRecords($notification->events)
            ->assertCountTableRecords(2);
    }

    public function test_the_stats_widget_matches_the_sdk_stats(): void
    {
        [$notification] = $this->sendMixed();

        Livewire::test(NotificationStats::class, ['record' => $notification])
            ->assertSee(['Targeted', '4', '50% of targeted', '50% of delivered']);
    }
}
