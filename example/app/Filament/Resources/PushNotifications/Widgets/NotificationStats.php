<?php

namespace App\Filament\Resources\PushNotifications\Widgets;

use App\Filament\Support\DeliveryStatCards;
use Filament\Widgets\StatsOverviewWidget;
use M0d1xd\ExpoNotification\Models\PushNotification;

class NotificationStats extends StatsOverviewWidget
{
    public ?PushNotification $record = null;

    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        return DeliveryStatCards::make($this->record->stats());
    }
}
