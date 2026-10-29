<?php

namespace App\Filament\Resources\PushNotifications;

use App\Filament\Resources\PushNotifications\Pages\ListPushNotifications;
use App\Filament\Resources\PushNotifications\Pages\ViewPushNotification;
use App\Filament\Resources\PushNotifications\RelationManagers\DeliveriesRelationManager;
use App\Filament\Resources\PushNotifications\RelationManagers\EventsRelationManager;
use App\Filament\Resources\PushNotifications\Schemas\PushNotificationInfolist;
use App\Filament\Resources\PushNotifications\Tables\PushNotificationsTable;
use App\Filament\Resources\PushNotifications\Widgets\NotificationStats;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use M0d1xd\ExpoNotification\Models\PushNotification;
use UnitEnum;

class PushNotificationResource extends Resource
{
    protected static ?string $model = PushNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Push';

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?string $modelLabel = 'notification';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

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
