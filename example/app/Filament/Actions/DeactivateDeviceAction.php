<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushTokenRegistry;

/**
 * Stops sending to a device. It comes back when the app registers again.
 */
class DeactivateDeviceAction
{
    public static function make(): Action
    {
        return Action::make('deactivate')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('The device stops receiving pushes until the app registers again.')
            ->visible(fn (PushToken $record): bool => $record->active)
            ->action(function (PushToken $record, PushTokenRegistry $registry) {
                $registry->deactivate($record);

                Notification::make()->title('Device deactivated')->success()->send();
            });
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('deactivate')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('The devices stop receiving pushes until the app registers again.')
            ->action(function (Collection $records, PushTokenRegistry $registry) {
                $records->where('active', true)->each(fn (PushToken $token) => $registry->deactivate($token));

                Notification::make()->title('Devices deactivated')->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
