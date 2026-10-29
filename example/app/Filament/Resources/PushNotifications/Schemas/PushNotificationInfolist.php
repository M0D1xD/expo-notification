<?php

namespace App\Filament\Resources\PushNotifications\Schemas;

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
                Section::make('Notification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('sent_at')->dateTime(),
                        TextEntry::make('body')->columnSpanFull(),
                        TextEntry::make('id')->label('Notification ID')->copyable(),
                        self::json('data')->placeholder('—'),
                    ]),
                Section::make('Payload as sent')
                    ->description('The message before the per-platform tracking payload (data-only on Android).')
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
