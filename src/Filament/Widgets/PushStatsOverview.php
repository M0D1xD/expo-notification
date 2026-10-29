<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use M0d1xd\ExpoNotification\DeliveryStats;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Filament\Support\DeliveryStatCards;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushToken;

class PushStatsOverview extends StatsOverviewWidget
{
    public const int DAYS = 7;

    protected static ?int $sort = 1;

    protected int|array|null $columns = 3;

    protected function getHeading(): ?string
    {
        return __('expo-notification::filament.widgets.overview_heading', ['days' => static::DAYS]);
    }

    protected function getPollingInterval(): ?string
    {
        return ExpoNotificationPlugin::get()->getPollingInterval();
    }

    protected function getStats(): array
    {
        $since = now()->subDays(static::DAYS);
        $devices = PushToken::query()->active()->toBase()
            ->selectRaw('platform, count(*) as total')
            ->groupBy('platform')
            ->pluck('total', 'platform');

        return [
            Stat::make(
                __('expo-notification::filament.widgets.notifications_sent'),
                PushNotification::query()->where('sent_at', '>=', $since)->count(),
            ),
            ...DeliveryStatCards::make(DeliveryStats::for(PushNotificationDelivery::query()->where('sent_at', '>=', $since))),
            Stat::make(__('expo-notification::filament.widgets.active_devices'), $devices->sum())
                ->description($devices->map(fn ($total, $platform) => "{$total} {$platform}")->join(', ')
                    ?: __('expo-notification::filament.widgets.no_devices')),
        ];
    }
}
