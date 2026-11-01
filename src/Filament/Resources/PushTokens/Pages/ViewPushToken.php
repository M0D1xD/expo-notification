<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use M0d1xd\ExpoNotification\Filament\Actions\DeactivateDeviceAction;
use M0d1xd\ExpoNotification\Filament\Actions\SendNotificationAction;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;
use M0d1xd\ExpoNotification\Filament\Support\OwnerKey;

class ViewPushToken extends ViewRecord
{
    protected static string $resource = PushTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendNotificationAction::toDevice(),
            DeactivateDeviceAction::make(),
            Action::make('ownerAnalysis')
                ->label(__('expo-notification::filament.devices.owner_action'))
                ->icon(Heroicon::OutlinedChartBar)
                ->color('gray')
                ->visible(fn (): bool => OwnerKey::fromDevice($this->getRecord()) !== null)
                ->url(fn (): string => PushTokenResource::getUrl('owner', ['owner' => OwnerKey::encode((string) OwnerKey::fromDevice($this->getRecord()))])),
        ];
    }
}
