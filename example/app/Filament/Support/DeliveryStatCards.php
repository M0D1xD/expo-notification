<?php

namespace App\Filament\Support;

use Filament\Widgets\StatsOverviewWidget\Stat;
use M0d1xd\ExpoNotification\DeliveryStats;

class DeliveryStatCards
{
    /** @return array<Stat> */
    public static function make(DeliveryStats $stats): array
    {
        return [
            Stat::make('Targeted', $stats->targeted)
                ->description("{$stats->accepted} accepted by Expo"),
            Stat::make('Delivered', $stats->delivered)
                ->description(Display::rate($stats->deliveryRate()).' of targeted, reported by the device')
                ->color('success'),
            Stat::make('Opened', $stats->opened)
                ->description(Display::rate($stats->openRate()).' of delivered')
                ->color('primary'),
            Stat::make('Failed', $stats->failed)
                ->description('Ticket or receipt error')
                ->color($stats->failed > 0 ? 'danger' : 'gray'),
        ];
    }
}
