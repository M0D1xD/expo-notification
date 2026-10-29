<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('expo-notification::filament.notifications.events');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('pushToken'))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('expo-notification::filament.events.occurred_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('pushToken.device_id')
                    ->label(__('expo-notification::filament.events.device'))
                    ->placeholder(__('expo-notification::filament.deliveries.deleted_device')),
                TextColumn::make('type')
                    ->label(__('expo-notification::filament.events.type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'opened' => 'success',
                        'received' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('source')
                    ->label(__('expo-notification::filament.events.source'))
                    ->placeholder('—'),
                TextColumn::make('app_state')
                    ->label(__('expo-notification::filament.events.app_state'))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('expo-notification::filament.events.type'))
                    ->options(__('expo-notification::filament.events.types')),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->poll(ExpoNotificationPlugin::get()->getPollingInterval());
    }
}
