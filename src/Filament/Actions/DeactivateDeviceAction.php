<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Actions;

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
            ->label(__('expo-notification::filament.actions.deactivate.label'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('expo-notification::filament.actions.deactivate.description'))
            ->visible(fn (PushToken $record): bool => $record->active)
            ->action(function (PushToken $record, PushTokenRegistry $registry) {
                $registry->deactivate($record);

                Notification::make()->title(__('expo-notification::filament.actions.deactivate.done'))->success()->send();
            });
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('deactivate')
            ->label(__('expo-notification::filament.actions.deactivate.label'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('expo-notification::filament.actions.deactivate.bulk_description'))
            ->action(function (Collection $records, PushTokenRegistry $registry) {
                $records->where('active', true)->each(fn (PushToken $token) => $registry->deactivate($token));

                Notification::make()->title(__('expo-notification::filament.actions.deactivate.bulk_done'))->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
