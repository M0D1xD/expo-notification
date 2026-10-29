<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Support;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;

/**
 * The deliveries table shared by a notification (one row per device) and a device (one row per notification).
 */
class DeliveryTable
{
    public static function configure(Table $table, bool $perDevice): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with($perDevice ? ['pushToken.owner'] : ['notification']))
            ->columns([
                ...($perDevice ? [
                    TextColumn::make('pushToken.device_id')
                        ->label(__('expo-notification::filament.deliveries.device'))
                        ->searchable()
                        ->placeholder(__('expo-notification::filament.deliveries.deleted_device')),
                    TextColumn::make('pushToken.platform')
                        ->label(__('expo-notification::filament.deliveries.platform'))
                        ->badge(),
                    TextColumn::make('owner')
                        ->label(__('expo-notification::filament.deliveries.owner'))
                        ->state(fn (PushNotificationDelivery $record): string => Display::owner($record->pushToken?->owner)),
                ] : [
                    TextColumn::make('notification.title')
                        ->label(__('expo-notification::filament.deliveries.notification'))
                        ->limit(40),
                ]),
                TextColumn::make('locale')
                    ->label(__('expo-notification::filament.deliveries.locale'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: ExpoNotificationPlugin::get()->getLocales() === []),
                TextColumn::make('ticket_status')
                    ->label(__('expo-notification::filament.deliveries.ticket'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'ok' ? 'success' : 'danger')
                    ->description(fn (PushNotificationDelivery $record): ?string => $record->ticket_id),
                TextColumn::make('receipt_status')
                    ->label(__('expo-notification::filament.deliveries.receipt'))
                    ->badge()
                    ->placeholder(__('expo-notification::filament.deliveries.pending'))
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('error_type')
                    ->label(__('expo-notification::filament.deliveries.error'))
                    ->color('danger')
                    ->placeholder('—')
                    ->tooltip(fn (PushNotificationDelivery $record): ?string => $record->error_message),
                TextColumn::make('sent_at')
                    ->label(__('expo-notification::filament.deliveries.sent_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivered_at')
                    ->label(__('expo-notification::filament.deliveries.delivered_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('opened_at')
                    ->label(__('expo-notification::filament.deliveries.opened_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('dismissed_at')
                    ->label(__('expo-notification::filament.deliveries.dismissed_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ...($perDevice ? [
                    SelectFilter::make('platform')
                        ->label(__('expo-notification::filament.deliveries.platform'))
                        ->options(__('expo-notification::filament.platforms'))
                        ->query(fn (Builder $query, array $data) => $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, string $platform) => $query->whereHas('pushToken', fn (Builder $q) => $q->where('platform', $platform)),
                        )),
                ] : []),
                TernaryFilter::make('delivered')
                    ->label(__('expo-notification::filament.deliveries.delivered'))
                    ->nullable()
                    ->attribute('delivered_at'),
                TernaryFilter::make('opened')
                    ->label(__('expo-notification::filament.deliveries.opened'))
                    ->nullable()
                    ->attribute('opened_at'),
                Filter::make('failed')
                    ->label(__('expo-notification::filament.deliveries.failed'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->failed()),
            ])
            ->defaultSort('sent_at', 'desc')
            ->poll(ExpoNotificationPlugin::get()->getPollingInterval());
    }
}
