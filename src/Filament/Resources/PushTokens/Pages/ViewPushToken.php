<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages;

use Filament\Resources\Pages\ViewRecord;
use M0d1xd\ExpoNotification\Filament\Actions\DeactivateDeviceAction;
use M0d1xd\ExpoNotification\Filament\Actions\SendNotificationAction;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;

class ViewPushToken extends ViewRecord
{
    protected static string $resource = PushTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendNotificationAction::toDevice(),
            DeactivateDeviceAction::make(),
        ];
    }
}
