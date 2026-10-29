<?php

namespace App\Filament\Resources\PushTokens\Pages;

use App\Filament\Actions\SendNotificationAction;
use App\Filament\Resources\PushTokens\PushTokenResource;
use Filament\Resources\Pages\ListRecords;

class ListPushTokens extends ListRecords
{
    protected static string $resource = PushTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendNotificationAction::make(),
        ];
    }
}
