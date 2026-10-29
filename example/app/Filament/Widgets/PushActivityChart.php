<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;

class PushActivityChart extends ChartWidget
{
    public const DAYS = 14;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Deliveries, last 14 days';

    protected ?string $pollingInterval = '10s';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = now()->subDays(self::DAYS - 1)->startOfDay();

        $rows = PushNotificationDelivery::query()
            ->where('sent_at', '>=', $start)
            ->toBase()
            ->selectRaw('DATE(sent_at) as day, count(*) as targeted')
            ->selectRaw('sum(case when delivered_at is not null then 1 else 0 end) as delivered')
            ->selectRaw('sum(case when opened_at is not null then 1 else 0 end) as opened')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $days = collect(range(0, self::DAYS - 1))->map(fn (int $offset) => $start->copy()->addDays($offset));
        $series = fn (string $column) => $days->map(fn (Carbon $day) => (int) ($rows[$day->toDateString()]->{$column} ?? 0))->all();

        return [
            'datasets' => [
                ['label' => 'Targeted', 'data' => $series('targeted'), 'borderColor' => '#94a3b8', 'backgroundColor' => '#94a3b8'],
                ['label' => 'Delivered', 'data' => $series('delivered'), 'borderColor' => '#22c55e', 'backgroundColor' => '#22c55e'],
                ['label' => 'Opened', 'data' => $series('opened'), 'borderColor' => '#f59e0b', 'backgroundColor' => '#f59e0b'],
            ],
            'labels' => $days->map(fn (Carbon $day) => $day->format('M j'))->all(),
        ];
    }
}
