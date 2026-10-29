<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature\Filament;

use Livewire\Livewire;
use M0d1xd\ExpoNotification\Filament\Widgets\PushActivityChart;
use M0d1xd\ExpoNotification\Filament\Widgets\PushStatsOverview;

class DashboardWidgetsTest extends FilamentTestCase
{
    /** The rejected device is deactivated, so 4 of the 5 devices are still active. */
    public function test_the_stats_cover_the_last_seven_days(): void
    {
        $this->travel(-10)->days();
        $this->send($this->device('old'));
        $this->travelBack();
        $this->sendMixed();

        Livewire::test(PushStatsOverview::class)
            ->assertSee(['Notifications sent', 'Targeted', '4', '50% of targeted', '50% of delivered', '4 android']);
    }

    public function test_the_chart_counts_today(): void
    {
        $this->sendMixed();

        $widget = Livewire::test(PushActivityChart::class)->instance();
        $data = (fn () => $this->getData())->call($widget);

        $this->assertCount(14, $data['labels']);
        $this->assertSame([4, 2, 1], array_map(fn (array $set) => end($set['data']), $data['datasets']));
    }
}
