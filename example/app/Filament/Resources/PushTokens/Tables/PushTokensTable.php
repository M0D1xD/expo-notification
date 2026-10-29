<?php

namespace App\Filament\Resources\PushTokens\Tables;

use App\Filament\Actions\DeactivateDeviceAction;
use App\Filament\Actions\SendNotificationAction;
use App\Filament\Support\Display;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use M0d1xd\ExpoNotification\Models\PushToken;

class PushTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('owner'))
            ->columns([
                TextColumn::make('device_id')
                    ->label('Device')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('platform')->badge(),
                TextColumn::make('locale')->placeholder('—'),
                TextColumn::make('owner')
                    ->state(fn (PushToken $record): string => Display::owner($record->owner))
                    ->color(fn (PushToken $record): ?string => $record->owner ? null : 'gray'),
                IconColumn::make('active')->boolean(),
                TextColumn::make('last_registered_at')
                    ->label('Last registered')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('deactivated_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('push_token')
                    ->label('Expo push token')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('platform')->options(['android' => 'Android', 'ios' => 'iOS']),
                TernaryFilter::make('active'),
                TernaryFilter::make('owner')
                    ->label('Owner')
                    ->placeholder('All devices')
                    ->trueLabel('Signed-in user')
                    ->falseLabel('Guest')
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
