<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Support;

use Filament\Widgets\StatsOverviewWidget\Stat;
use M0d1xd\ExpoNotification\DeliveryStats;

class DeliveryStatCards
{
    /** @return array<Stat> */
    public static function make(DeliveryStats $stats): array
    {
        return [
            Stat::make(__('expo-notification::filament.stats.targeted'), $stats->targeted)
                ->description(__('expo-notification::filament.stats.accepted', ['count' => $stats->accepted])),
            Stat::make(__('expo-notification::filament.stats.delivered'), $stats->delivered)
                ->description(__('expo-notification::filament.stats.delivered_description', ['rate' => Display::rate($stats->deliveryRate())]))
                ->color('success'),
            Stat::make(__('expo-notification::filament.stats.opened'), $stats->opened)
                ->description(__('expo-notification::filament.stats.opened_description', ['rate' => Display::rate($stats->openRate())]))
                ->color('primary'),
            Stat::make(__('expo-notification::filament.stats.failed'), $stats->failed)
                ->description(__('expo-notification::filament.stats.failed_description'))
                ->color($stats->failed > 0 ? 'danger' : 'gray'),
        ];
    }
}
