<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Tables;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Filament\Support\Display;
use M0d1xd\ExpoNotification\Models\PushNotification;

class PushNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withDeliveryStats())
            ->columns([
                TextColumn::make('sent_at')
                    ->label(__('expo-notification::filament.notifications.columns.sent_at'))
                    ->dateTime()
                    ->sortable()
                    ->description(fn (PushNotification $record): ?string => $record->sent_at?->diffForHumans()),
                TextColumn::make('title')
                    ->label(__('expo-notification::filament.notifications.columns.title'))
                    ->searchable()
                    ->limit(40)
                    ->description(fn (PushNotification $record): string => Str::limit((string) $record->body, 60)),
                TextColumn::make('deliveries_count')
                    ->label(__('expo-notification::filament.notifications.columns.targeted'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('delivered_count')
                    ->label(__('expo-notification::filament.notifications.columns.delivered'))
                    ->numeric()
                    ->sortable()
                    ->description(fn (PushNotification $record): string => Display::rate($record->stats()->deliveryRate())),
                TextColumn::make('opened_count')
                    ->label(__('expo-notification::filament.notifications.columns.opened'))
                    ->numeric()
                    ->sortable()
                    ->description(fn (PushNotification $record): string => Display::rate($record->stats()->openRate())),
                TextColumn::make('failed_count')
                    ->label(__('expo-notification::filament.notifications.columns.failed'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('sent_at')
                    ->label(__('expo-notification::filament.notifications.filters.sent_at'))
                    ->schema([
                        DatePicker::make('from')->label(__('expo-notification::filament.notifications.filters.from')),
                        DatePicker::make('until')->label(__('expo-notification::filament.notifications.filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('sent_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('sent_at', '<=', $date))),
                Filter::make('has_failures')
                    ->label(__('expo-notification::filament.notifications.filters.has_failures'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('deliveries', fn (Builder $deliveries) => $deliveries->failed())),
            ])
            ->defaultSort('sent_at', 'desc')
            ->poll(ExpoNotificationPlugin::get()->getPollingInterval())
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
