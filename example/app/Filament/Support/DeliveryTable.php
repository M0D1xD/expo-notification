<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                        ->label('Device')
                        ->searchable()
                        ->placeholder('Deleted device'),
                    TextColumn::make('pushToken.platform')
                        ->label('Platform')
                        ->badge(),
                    TextColumn::make('owner')
                        ->state(fn (PushNotificationDelivery $record): string => Display::owner($record->pushToken?->owner)),
                ] : [
                    TextColumn::make('notification.title')
                        ->label('Notification')
                        ->limit(40),
                ]),
                TextColumn::make('ticket_status')
                    ->label('Ticket')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'ok' ? 'success' : 'danger')
                    ->description(fn (PushNotificationDelivery $record): ?string => $record->ticket_id),
                TextColumn::make('receipt_status')
                    ->label('Receipt')
                    ->badge()
                    ->placeholder('pending')
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('error_type')
                    ->label('Error')
                    ->color('danger')
                    ->placeholder('—')
                    ->tooltip(fn (PushNotificationDelivery $record): ?string => $record->error_message),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivered_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('opened_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('dismissed_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ...($perDevice ? [
                    SelectFilter::make('platform')
                        ->options(['android' => 'Android', 'ios' => 'iOS'])
                        ->query(fn (Builder $query, array $data) => $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, string $platform) => $query->whereHas('pushToken', fn (Builder $q) => $q->where('platform', $platform)),
                        )),
                ] : []),
                TernaryFilter::make('delivered')->nullable()->attribute('delivered_at'),
                TernaryFilter::make('opened')->nullable()->attribute('opened_at'),
                Filter::make('failed')->toggle()->query(fn (Builder $query) => $query->failed()),
            ])
            ->defaultSort('sent_at', 'desc')
            ->poll('10s');
    }
}
