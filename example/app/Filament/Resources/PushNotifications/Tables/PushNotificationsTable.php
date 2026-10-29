<?php

namespace App\Filament\Resources\PushNotifications\Tables;

use App\Filament\Support\Display;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use M0d1xd\ExpoNotification\Models\PushNotification;

class PushNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withDeliveryStats())
            ->columns([
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable()
                    ->description(fn (PushNotification $record): ?string => $record->sent_at?->diffForHumans()),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->description(fn (PushNotification $record): string => Str::limit((string) $record->body, 60)),
                TextColumn::make('deliveries_count')
                    ->label('Targeted')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('delivered_count')
                    ->label('Delivered')
                    ->numeric()
                    ->sortable()
                    ->description(fn (PushNotification $record): string => Display::rate($record->stats()->deliveryRate())),
                TextColumn::make('opened_count')
                    ->label('Opened')
                    ->numeric()
                    ->sortable()
                    ->description(fn (PushNotification $record): string => Display::rate($record->stats()->openRate())),
                TextColumn::make('failed_count')
                    ->label('Failed')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('sent_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('sent_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('sent_at', '<=', $date))),
                Filter::make('has_failures')
                    ->label('Has failures')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('deliveries', fn (Builder $deliveries) => $deliveries->failed())),
            ])
            ->defaultSort('sent_at', 'desc')
            ->poll('10s')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
