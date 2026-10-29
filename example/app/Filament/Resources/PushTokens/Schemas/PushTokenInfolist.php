<?php

namespace App\Filament\Resources\PushTokens\Schemas;

use App\Filament\Support\Display;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use M0d1xd\ExpoNotification\Models\PushToken;

class PushTokenInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Device')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('device_id')->copyable(),
                        TextEntry::make('platform')->badge(),
                        TextEntry::make('locale')->placeholder('—'),
                        TextEntry::make('owner')
                            ->state(fn (PushToken $record): string => Display::owner($record->owner)),
                        IconEntry::make('active')->boolean(),
                        TextEntry::make('last_registered_at')->dateTime(),
                        TextEntry::make('push_token')->label('Expo push token')->copyable()->columnSpan(2),
                        TextEntry::make('deactivated_at')->dateTime()->placeholder('—'),
                    ]),
            ]);
    }
}
