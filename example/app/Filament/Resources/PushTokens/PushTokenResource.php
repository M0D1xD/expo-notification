<?php

namespace App\Filament\Resources\PushTokens;

use App\Filament\Resources\PushTokens\Pages\ListPushTokens;
use App\Filament\Resources\PushTokens\Pages\ViewPushToken;
use App\Filament\Resources\PushTokens\RelationManagers\DeliveriesRelationManager;
use App\Filament\Resources\PushTokens\Schemas\PushTokenInfolist;
use App\Filament\Resources\PushTokens\Tables\PushTokensTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use M0d1xd\ExpoNotification\Models\PushToken;
use UnitEnum;

class PushTokenResource extends Resource
{
    protected static ?string $model = PushToken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Push';

    protected static ?string $navigationLabel = 'Devices';

    protected static ?string $modelLabel = 'device';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'device_id';

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
