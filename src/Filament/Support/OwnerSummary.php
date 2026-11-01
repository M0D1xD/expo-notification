<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;
use M0d1xd\ExpoNotification\Models\PushToken;

/**
 * One owner's devices at a glance, for the "Owner" group header: counts per platform, how many are active and when
 * the newest one registered. Covers all their devices, whatever the table filters.
 */
final readonly class OwnerSummary
{
    /** @param  array<string, int>  $platforms  devices per platform */
    public function __construct(
        public ?string $key,
        public int $devices,
        public int $active,
        public array $platforms,
        public ?Carbon $lastRegisteredAt,
    ) {}

    /** One aggregate query over the owner's devices (all guests for a guest device). */
    public static function forKey(?string $key): self
    {
        $rows = OwnerKey::scope(PushToken::query(), $key)
            ->toBase()
            ->selectRaw('platform, count(*) as devices, sum(case when active then 1 else 0 end) as active, max(last_registered_at) as last_registered_at')
            ->groupBy('platform')
            ->orderBy('platform')
            ->get();

        $last = $rows->pluck('last_registered_at')->filter()->max();

        return new self(
            key: $key,
            devices: (int) $rows->sum('devices'),
            active: (int) $rows->sum('active'),
            platforms: $rows->mapWithKeys(fn (object $row) => [(string) $row->platform => (int) $row->devices])->all(),
            lastRegisteredAt: $last === null ? null : Carbon::parse($last),
        );
    }

    /** "3 devices (2 active) · android ×2 · ios ×1 · last registered 3 hours ago · Full analysis →" */
    public function description(): Htmlable
    {
        $parts = [
            e(trans_choice('expo-notification::filament.devices.groups.devices', $this->devices, ['count' => $this->devices, 'active' => $this->active])),
            ...array_map(fn (string $platform, int $count) => e("{$platform} ×{$count}"), array_keys($this->platforms), $this->platforms),
        ];

        if ($this->key !== null) {
            if ($this->lastRegisteredAt !== null) {
                $parts[] = e(__('expo-notification::filament.devices.groups.last_registered', ['time' => $this->lastRegisteredAt->diffForHumans()]));
            }

            $parts[] = Blade::render('<x-filament::link :href="$href" size="sm" x-on:click.stop>{{ $label }}</x-filament::link>', [
                'href' => PushTokenResource::getUrl('owner', ['owner' => OwnerKey::encode($this->key)]),
                'label' => __('expo-notification::filament.devices.groups.analysis'),
            ]);
        }

        return new HtmlString(implode(' · ', $parts));
    }
}
