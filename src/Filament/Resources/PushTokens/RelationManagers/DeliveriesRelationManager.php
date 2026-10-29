<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushTokens\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use M0d1xd\ExpoNotification\Filament\Support\DeliveryTable;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('expo-notification::filament.devices.deliveries');
    }

    public function table(Table $table): Table
    {
        return DeliveryTable::configure($table, perDevice: false);
    }
}
