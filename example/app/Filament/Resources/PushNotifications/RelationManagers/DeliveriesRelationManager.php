<?php

namespace App\Filament\Resources\PushNotifications\RelationManagers;

use App\Filament\Support\DeliveryTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    public function table(Table $table): Table
    {
        return DeliveryTable::configure($table, perDevice: true);
    }
}
