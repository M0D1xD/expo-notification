<?php

namespace App\Filament\Resources\PushNotifications\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('pushToken'))
            ->columns([
                TextColumn::make('occurred_at')->dateTime()->sortable(),
                TextColumn::make('pushToken.device_id')->label('Device')->placeholder('Deleted device'),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'opened' => 'success',
                        'received' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('source')->placeholder('—'),
                TextColumn::make('app_state')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    'received' => 'Received',
                    'opened' => 'Opened',
                    'dismissed' => 'Dismissed',
                ]),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->poll('10s');
    }
}
