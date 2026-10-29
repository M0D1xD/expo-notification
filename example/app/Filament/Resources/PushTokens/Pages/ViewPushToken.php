<?php

namespace App\Filament\Resources\PushTokens\Pages;

use App\Filament\Actions\DeactivateDeviceAction;
use App\Filament\Actions\SendNotificationAction;
use App\Filament\Resources\PushTokens\PushTokenResource;
use Filament\Resources\Pages\ViewRecord;

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
