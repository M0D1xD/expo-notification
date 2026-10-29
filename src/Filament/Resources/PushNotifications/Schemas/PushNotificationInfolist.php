<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use M0d1xd\ExpoNotification\Models\PushNotification;

class PushNotificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('expo-notification::filament.notifications.infolist.section'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')->label(__('expo-notification::filament.notifications.infolist.title')),
                        TextEntry::make('sent_at')->label(__('expo-notification::filament.notifications.infolist.sent_at'))->dateTime(),
                        TextEntry::make('body')->label(__('expo-notification::filament.notifications.infolist.body'))->columnSpanFull(),
                        TextEntry::make('id')->label(__('expo-notification::filament.notifications.infolist.id'))->copyable(),
                        self::json('data')->label(__('expo-notification::filament.notifications.infolist.data'))->placeholder('—'),
                    ]),
                Section::make(__('expo-notification::filament.notifications.infolist.translations'))
                    ->description(__('expo-notification::filament.notifications.infolist.translations_description'))
                    ->visible(fn (PushNotification $record): bool => filled($record->translations))
                    ->collapsed()
                    ->schema([
                        self::json('translations')->hiddenLabel(),
                    ]),
                Section::make(__('expo-notification::filament.notifications.infolist.payload'))
                    ->description(__('expo-notification::filament.notifications.infolist.payload_description'))
                    ->collapsed()
                    ->schema([
                        self::json('payload')->hiddenLabel(),
                    ]),
            ]);
    }

    private static function json(string $attribute): TextEntry
    {
        return TextEntry::make($attribute)
            ->state(fn (PushNotification $record): ?string => $record->{$attribute} === null
                ? null
                : json_encode($record->{$attribute}, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->formatStateUsing(fn (string $state): HtmlString => new HtmlString(
                '<pre style="white-space: pre-wrap; font-family: ui-monospace, monospace; font-size: 0.75rem">'.e($state).'</pre>',
            ));
    }
}
