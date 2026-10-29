<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushNotifications;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Pages\ListPushNotifications;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Pages\ViewPushNotification;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\RelationManagers\DeliveriesRelationManager;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\RelationManagers\EventsRelationManager;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Schemas\PushNotificationInfolist;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Tables\PushNotificationsTable;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\Widgets\NotificationStats;
use M0d1xd\ExpoNotification\Models\PushNotification;
use UnitEnum;

class PushNotificationResource extends Resource
{
    protected static ?string $model = PushNotification::class;

    protected static ?string $slug = 'push-notifications';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return ExpoNotificationPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return ExpoNotificationPlugin::get()->getNavigationLabel('notifications');
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return ExpoNotificationPlugin::get()->getNavigationIcon('notifications');
    }

    public static function getNavigationSort(): ?int
    {
        return ExpoNotificationPlugin::get()->getNavigationSort('notifications');
    }

    public static function getModelLabel(): string
    {
        return __('expo-notification::filament.notifications.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return ExpoNotificationPlugin::get()->getPluralLabel('notifications');
    }

    public static function infolist(Schema $schema): Schema
    {
        return PushNotificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PushNotificationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DeliveriesRelationManager::class,
            EventsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            NotificationStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPushNotifications::route('/'),
            'view' => ViewPushNotification::route('/{record}'),
        ];
    }
}
