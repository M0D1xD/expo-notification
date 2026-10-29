<?php

namespace App\Filament\Resources\PushNotifications\Pages;

use App\Filament\Actions\SendNotificationAction;
use App\Filament\Resources\PushNotifications\PushNotificationResource;
use App\Filament\Resources\PushNotifications\Widgets\NotificationStats;
use Filament\Resources\Pages\ViewRecord;

class ViewPushNotification extends ViewRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendNotificationAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            NotificationStats::class,
        ];
    }
}
