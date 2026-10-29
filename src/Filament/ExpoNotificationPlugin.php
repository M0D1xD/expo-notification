<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament;

use BackedEnum;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\PushNotificationResource;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;
use M0d1xd\ExpoNotification\Filament\Widgets\PushActivityChart;
use M0d1xd\ExpoNotification\Filament\Widgets\PushStatsOverview;
use UnitEnum;

/**
 * Adds the "Push notifications" section to a panel: ->plugin(ExpoNotificationPlugin::make()).
 */
class ExpoNotificationPlugin implements Plugin
{
    public const string ID = 'expo-notification';

    /** False until navigationGroup() is called, so null can mean "no group". */
    private string|UnitEnum|false|null $navigationGroup = false;

    private int $navigationSort = 1;

    /** @var array<'notifications'|'devices', array{label: ?string, icon: string|BackedEnum|null, sort: ?int, enabled: bool}> */
    private array $resources = [
        'notifications' => ['label' => null, 'icon' => Heroicon::OutlinedBellAlert, 'sort' => null, 'enabled' => true],
        'devices' => ['label' => null, 'icon' => Heroicon::OutlinedDevicePhoneMobile, 'sort' => null, 'enabled' => true],
    ];

    /** @var list<class-string> */
    private array $widgets = [PushStatsOverview::class, PushActivityChart::class];

    private bool $sending = true;

    /** @var class-string|null */
    private ?string $ownerModel = null;

    private string $ownerTitleAttribute = 'name';

    private ?string $pollingInterval = '10s';

    public static function make(): static
    {
        return app(static::class);
    }

    /** The plugin registered on the current panel, or the defaults when it is used outside one. */
    public static function get(): static
    {
        $panel = Filament::getCurrentPanel();

        /** @var static */
        return $panel?->hasPlugin(self::ID) ? $panel->getPlugin(self::ID) : static::make();
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources(array_values(array_filter([
                $this->isEnabled('notifications') ? PushNotificationResource::class : null,
                $this->isEnabled('devices') ? PushTokenResource::class : null,
            ])))
            ->widgets($this->widgets);
    }

    public function boot(Panel $panel): void {}

    /** Null puts the items at the top level of the navigation. */
    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    /** Sort of the first item; the second item follows it. */
    public function navigationSort(int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function notificationsResource(?string $label = null, string|BackedEnum|null $icon = null, ?int $sort = null, bool $enabled = true): static
    {
        return $this->configureResource('notifications', $label, $icon, $sort, $enabled);
    }

    public function devicesResource(?string $label = null, string|BackedEnum|null $icon = null, ?int $sort = null, bool $enabled = true): static
    {
        return $this->configureResource('devices', $label, $icon, $sort, $enabled);
    }

    /**
     * Widgets added to the panel's dashboard; [] adds none.
     *
     * @param  list<class-string>  $widgets
     */
    public function widgets(array $widgets): static
    {
        $this->widgets = $widgets;

        return $this;
    }

    /** False hides every "Send notification" action. */
    public function sending(bool $enabled = true): static
    {
        $this->sending = $enabled;

        return $this;
    }

    /**
     * The model listed under "Chosen users" when sending. Defaults to `owner.model` in the "foreign" owner mode.
     *
     * @param  class-string|null  $model
     */
    public function ownerModel(?string $model, string $titleAttribute = 'name'): static
    {
        $this->ownerModel = $model;
        $this->ownerTitleAttribute = $titleAttribute;

        return $this;
    }

    /** How often tables and widgets refresh; null turns polling off. */
    public function pollingInterval(?string $interval): static
    {
        $this->pollingInterval = $interval;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup === false ? __('expo-notification::filament.navigation.group') : $this->navigationGroup;
    }

    /** @param  'notifications'|'devices'  $resource */
    public function getNavigationLabel(string $resource): string
    {
        return $this->resources[$resource]['label'] ?? __("expo-notification::filament.{$resource}.navigation_label");
    }

    /**
     * A custom label also names the pages (title, breadcrumb); otherwise the translated plural.
     *
     * @param  'notifications'|'devices'  $resource
     */
    public function getPluralLabel(string $resource): string
    {
        return $this->resources[$resource]['label'] ?? __("expo-notification::filament.{$resource}.plural_model_label");
    }

    /** @param  'notifications'|'devices'  $resource */
    public function getNavigationIcon(string $resource): string|BackedEnum|null
    {
        return $this->resources[$resource]['icon'];
    }

    /** @param  'notifications'|'devices'  $resource */
    public function getNavigationSort(string $resource): int
    {
        return $this->resources[$resource]['sort'] ?? $this->navigationSort + array_search($resource, array_keys($this->resources), true);
    }

    /** @param  'notifications'|'devices'  $resource */
    public function isEnabled(string $resource): bool
    {
        return $this->resources[$resource]['enabled'];
    }

    /** @return list<class-string> */
    public function getWidgets(): array
    {
        return $this->widgets;
    }

    public function isSendingEnabled(): bool
    {
        return $this->sending;
    }

    /** @return class-string|null */
    public function getOwnerModel(): ?string
    {
        if ($this->ownerModel !== null) {
            return $this->ownerModel;
        }

        return config('expo-notification.owner.mode') === 'foreign' ? config('expo-notification.owner.model') : null;
    }

    public function getOwnerTitleAttribute(): string
    {
        return $this->ownerTitleAttribute;
    }

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    /** @param  'notifications'|'devices'  $resource */
    private function configureResource(string $resource, ?string $label, string|BackedEnum|null $icon, ?int $sort, bool $enabled): static
    {
        $this->resources[$resource] = [
            'label' => $label,
            'icon' => $icon ?? $this->resources[$resource]['icon'],
            'sort' => $sort,
            'enabled' => $enabled,
        ];

        return $this;
    }
}
