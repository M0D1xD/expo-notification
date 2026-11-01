<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Livewire\Attributes\Locked;
use M0d1xd\ExpoNotification\DeliveryStats;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages\OwnerAnalysis;
use M0d1xd\ExpoNotification\Filament\Support\DeliveryStatCards;

/** Delivery and engagement across every device of one owner. */
class OwnerStats extends StatsOverviewWidget
{
    #[Locked]
    public string $ownerKey = '';

    protected function getPollingInterval(): ?string
    {
        return ExpoNotificationPlugin::get()->getPollingInterval();
    }

    protected function getStats(): array
    {
        return DeliveryStatCards::make(DeliveryStats::for(OwnerAnalysis::deliveriesQuery($this->ownerKey)));
    }
}
