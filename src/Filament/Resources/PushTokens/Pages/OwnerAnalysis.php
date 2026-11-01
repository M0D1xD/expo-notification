<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Pages;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Locked;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Widgets\OwnerStats;
use M0d1xd\ExpoNotification\Filament\Support\DeliveryTable;
use M0d1xd\ExpoNotification\Filament\Support\DeliveryTableContext;
use M0d1xd\ExpoNotification\Filament\Support\Display;
use M0d1xd\ExpoNotification\Filament\Support\OwnerKey;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushToken;

/**
 * Everything sent to one owner: their devices, delivery stats, and every notification to every device with whether it
 * was received. Reached from the "Owner" group header on the Devices list and from a device's page.
 */
class OwnerAnalysis extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PushTokenResource::class;

    /** The {@see OwnerKey}, decoded from the URL. */
    #[Locked]
    public string $ownerKey;

    /** @var Collection<int, PushToken>|null */
    private ?Collection $devices = null;

    public function mount(string $owner): void
    {
        $key = OwnerKey::decode($owner);

        try {
            $exists = $key !== null && OwnerKey::scope(PushToken::query(), $key)->exists();
        } catch (QueryException) {
            // e.g. Postgres rejecting "abc" for a uuid/bigint owner column: still just an unknown owner.
            $exists = false;
        }

        abort_unless($exists, 404);

        $this->ownerKey = $key;
    }

    /** @return Builder<PushNotificationDelivery> */
    public static function deliveriesQuery(string $ownerKey): Builder
    {
        return PushNotificationDelivery::query()
            ->whereHas('pushToken', fn (Builder $query) => OwnerKey::scope($query, $ownerKey));
    }

    /** @return Collection<int, PushToken> newest registration first */
    public function getDevices(): Collection
    {
        return $this->devices ??= OwnerKey::scope(PushToken::query(), $this->ownerKey)
            ->with('owner')
            ->orderByDesc('last_registered_at')
            ->get();
    }

    public static function notificationGroupTitle(PushNotificationDelivery $record): string
    {
        $title = $record->notification?->title ?? __('expo-notification::filament.owner_analysis.deleted_notification');
        $sentAt = $record->notification?->created_at ?? $record->sent_at;

        return $sentAt === null ? $title : $title.' · '.$sentAt->format('M j, Y H:i:s');
    }

    public function getTitle(): string
    {
        return Display::owner($this->getDevices()->first()?->owner);
    }

    public function getBreadcrumb(): string
    {
        return __('expo-notification::filament.owner_analysis.breadcrumb');
    }

    protected function getHeaderWidgets(): array
    {
        return [OwnerStats::make(['ownerKey' => $this->ownerKey])];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('expo-notification::filament.owner_analysis.devices'))
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('devices')
                        ->hiddenLabel()
                        ->state(fn (): Collection => $this->getDevices())
                        ->columns(4)
                        ->schema([
                            TextEntry::make('device_id')
                                ->label(__('expo-notification::filament.devices.columns.device'))
                                ->url(fn (PushToken $record): string => PushTokenResource::getUrl('view', ['record' => $record])),
                            TextEntry::make('platform')
                                ->label(__('expo-notification::filament.devices.columns.platform'))
                                ->badge(),
                            IconEntry::make('active')
                                ->label(__('expo-notification::filament.devices.columns.active'))
                                ->boolean(),
                            TextEntry::make('last_registered_at')
                                ->label(__('expo-notification::filament.devices.columns.last_registered_at'))
                                ->since()
                                ->dateTimeTooltip()
                                ->placeholder('—'),
                        ]),
                ]),
            Section::make(__('expo-notification::filament.owner_analysis.deliveries'))
                ->schema([EmbeddedTable::make()]),
        ]);
    }

    public function table(Table $table): Table
    {
        return DeliveryTable::configure($table, DeliveryTableContext::Owner)
            ->query(fn (): Builder => static::deliveriesQuery($this->ownerKey))
            ->groups([
                Group::make('push_notification_id')
                    ->label(__('expo-notification::filament.deliveries.notification'))
                    // Filament remembers collapsed groups by title, so the send time keeps same-titled notifications apart.
                    ->getTitleFromRecordUsing(fn (PushNotificationDelivery $record): string => self::notificationGroupTitle($record))
                    // Newest notification first; the id keeps one notification's rows together within the same second.
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('sent_at', $direction)
                        ->orderBy('push_notification_id', $direction))
                    ->collapsible(),
            ])
            ->defaultGroup('push_notification_id', 'desc');
    }
}
