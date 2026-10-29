<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Filament\Support\DeliveryStatCards;
use M0d1xd\ExpoNotification\Models\PushNotification;

class NotificationStats extends StatsOverviewWidget
{
    public ?PushNotification $record = null;

    protected function getPollingInterval(): ?string
    {
        return ExpoNotificationPlugin::get()->getPollingInterval();
    }

    protected function getStats(): array
    {
        return DeliveryStatCards::make($this->record->stats());
    }
}
