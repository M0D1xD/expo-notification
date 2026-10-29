<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages;

use Filament\Resources\Pages\ListRecords;
use M0d1xd\ExpoNotification\Filament\Actions\SendNotificationAction;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;

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
