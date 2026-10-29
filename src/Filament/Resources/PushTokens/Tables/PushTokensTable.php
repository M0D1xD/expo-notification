<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use M0d1xd\ExpoNotification\Filament\Actions\DeactivateDeviceAction;
use M0d1xd\ExpoNotification\Filament\Actions\SendNotificationAction;
use M0d1xd\ExpoNotification\Filament\Support\Display;
use M0d1xd\ExpoNotification\Models\PushToken;

class PushTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('owner'))
            ->columns([
                TextColumn::make('device_id')
                    ->label(__('expo-notification::filament.devices.columns.device'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('platform')
                    ->label(__('expo-notification::filament.devices.columns.platform'))
                    ->badge(),
                TextColumn::make('locale')
                    ->label(__('expo-notification::filament.devices.columns.locale'))
                    ->placeholder('—'),
                TextColumn::make('owner')
                    ->label(__('expo-notification::filament.devices.columns.owner'))
                    ->state(fn (PushToken $record): string => Display::owner($record->owner))
                    ->color(fn (PushToken $record): ?string => $record->owner ? null : 'gray'),
                IconColumn::make('active')
                    ->label(__('expo-notification::filament.devices.columns.active'))
                    ->boolean(),
                TextColumn::make('last_registered_at')
                    ->label(__('expo-notification::filament.devices.columns.last_registered_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('deactivated_at')
                    ->label(__('expo-notification::filament.devices.columns.deactivated_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('push_token')
                    ->label(__('expo-notification::filament.devices.columns.push_token'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->label(__('expo-notification::filament.devices.columns.platform'))
                    ->options(__('expo-notification::filament.platforms')),
                TernaryFilter::make('active')
                    ->label(__('expo-notification::filament.devices.columns.active')),
                TernaryFilter::make('owner')
                    ->label(__('expo-notification::filament.devices.columns.owner'))
                    ->placeholder(__('expo-notification::filament.devices.filters.all'))
                    ->trueLabel(__('expo-notification::filament.devices.filters.signed_in'))
                    ->falseLabel(__('expo-notification::filament.devices.filters.guest'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull((new PushToken)->owner()->getForeignKeyName()),
                        false: fn (Builder $query) => $query->whereNull((new PushToken)->owner()->getForeignKeyName()),
                    ),
            ])
            ->defaultSort('last_registered_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    SendNotificationAction::toDevice(),
                    DeactivateDeviceAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SendNotificationAction::toDevices(),
                    DeactivateDeviceAction::bulk(),
                ]),
            ]);
    }
}
