<?php

namespace App\Filament\Resources\PushNotifications\Pages;

use App\Filament\Actions\SendNotificationAction;
use App\Filament\Resources\PushNotifications\PushNotificationResource;
use Filament\Resources\Pages\ListRecords;

class ListPushNotifications extends ListRecords
{
    protected static string $resource = PushNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendNotificationAction::make(),
        ];
    }
}
