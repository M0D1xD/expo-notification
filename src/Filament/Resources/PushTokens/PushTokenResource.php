<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushTokens;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages\ListPushTokens;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages\ViewPushToken;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\RelationManagers\DeliveriesRelationManager;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Schemas\PushTokenInfolist;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Tables\PushTokensTable;
use M0d1xd\ExpoNotification\Models\PushToken;
use UnitEnum;

class PushTokenResource extends Resource
{
    protected static ?string $model = PushToken::class;

    protected static ?string $slug = 'push-tokens';

    protected static ?string $recordTitleAttribute = 'device_id';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return ExpoNotificationPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return ExpoNotificationPlugin::get()->getNavigationLabel('devices');
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return ExpoNotificationPlugin::get()->getNavigationIcon('devices');
    }

    public static function getNavigationSort(): ?int
    {
        return ExpoNotificationPlugin::get()->getNavigationSort('devices');
    }

    public static function getModelLabel(): string
    {
        return __('expo-notification::filament.devices.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return ExpoNotificationPlugin::get()->getPluralLabel('devices');
    }

    public static function infolist(Schema $schema): Schema
    {
        return PushTokenInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PushTokensTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPushTokens::route('/'),
            'view' => ViewPushToken::route('/{record}'),
        ];
    }
}
