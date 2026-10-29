<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Pages;

use Filament\Resources\Pages\ViewRecord;
use M0d1xd\ExpoNotification\Filament\Actions\SendNotificationAction;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\PushNotificationResource;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Widgets\NotificationStats;

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
