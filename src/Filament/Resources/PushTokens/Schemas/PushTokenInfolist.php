<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use M0d1xd\ExpoNotification\Filament\Support\Display;
use M0d1xd\ExpoNotification\Models\PushToken;

class PushTokenInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('expo-notification::filament.devices.section'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('device_id')->label(__('expo-notification::filament.devices.columns.device'))->copyable(),
                        TextEntry::make('platform')->label(__('expo-notification::filament.devices.columns.platform'))->badge(),
                        TextEntry::make('locale')->label(__('expo-notification::filament.devices.columns.locale'))->placeholder('—'),
                        TextEntry::make('owner')
                            ->label(__('expo-notification::filament.devices.columns.owner'))
                            ->state(fn (PushToken $record): string => Display::owner($record->owner)),
                        IconEntry::make('active')->label(__('expo-notification::filament.devices.columns.active'))->boolean(),
                        TextEntry::make('last_registered_at')
                            ->label(__('expo-notification::filament.devices.columns.last_registered_at'))
                            ->dateTime(),
                        TextEntry::make('push_token')
                            ->label(__('expo-notification::filament.devices.columns.push_token'))
                            ->copyable()
                            ->columnSpan(2),
                        TextEntry::make('deactivated_at')
                            ->label(__('expo-notification::filament.devices.columns.deactivated_at'))
                            ->dateTime()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
