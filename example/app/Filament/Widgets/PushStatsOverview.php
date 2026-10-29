<?php

namespace App\Filament\Widgets;

use App\Filament\Support\DeliveryStatCards;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use M0d1xd\ExpoNotification\DeliveryStats;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushToken;

class PushStatsOverview extends StatsOverviewWidget
{
    public const DAYS = 7;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Push, last 7 days';

    protected ?string $pollingInterval = '10s';

    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $since = now()->subDays(self::DAYS);
        $devices = PushToken::query()->active()->toBase()
            ->selectRaw('platform, count(*) as total')
            ->groupBy('platform')
            ->pluck('total', 'platform');

        return [
            Stat::make('Notifications sent', PushNotification::query()->where('sent_at', '>=', $since)->count()),
            ...DeliveryStatCards::make(DeliveryStats::for(PushNotificationDelivery::query()->where('sent_at', '>=', $since))),
            Stat::make('Active devices', $devices->sum())
                ->description($devices->map(fn ($total, $platform) => "{$total} {$platform}")->join(', ') ?: 'None registered'),
        ];
    }
}
