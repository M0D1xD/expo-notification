<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Resources\PushTokens\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use M0d1xd\ExpoNotification\Filament\Actions\DeactivateDeviceAction;
use M0d1xd\ExpoNotification\Filament\Actions\SendNotificationAction;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Filament\Support\Display;
use M0d1xd\ExpoNotification\Filament\Support\OwnerKey;
use M0d1xd\ExpoNotification\Filament\Support\OwnerSummary;
use M0d1xd\ExpoNotification\Models\PushToken;

class PushTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('owner'))
            ->columns([
                TextColumn::make('device_id')
                    ->label(__('expo-notification::filament.devices.columns.device'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('platform')
                    ->label(__('expo-notification::filament.devices.columns.platform'))
                    ->badge(),
                TextColumn::make('locale')
                    ->label(__('expo-notification::filament.devices.columns.locale'))
                    ->placeholder('—'),
                TextColumn::make('owner')
                    ->label(__('expo-notification::filament.devices.columns.owner'))
                    ->state(fn (PushToken $record): string => Display::owner($record->owner))
                    ->color(fn (PushToken $record): ?string => $record->owner ? null : 'gray'),
                IconColumn::make('active')
                    ->label(__('expo-notification::filament.devices.columns.active'))
                    ->boolean(),
                TextColumn::make('last_registered_at')
                    ->label(__('expo-notification::filament.devices.columns.last_registered_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('deactivated_at')
                    ->label(__('expo-notification::filament.devices.columns.deactivated_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('push_token')
                    ->label(__('expo-notification::filament.devices.columns.push_token'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->label(__('expo-notification::filament.devices.columns.platform'))
                    ->options(__('expo-notification::filament.platforms')),
                TernaryFilter::make('active')
                    ->label(__('expo-notification::filament.devices.columns.active')),
                TernaryFilter::make('owner')
                    ->label(__('expo-notification::filament.devices.columns.owner'))
                    ->placeholder(__('expo-notification::filament.devices.filters.all'))
                    ->trueLabel(__('expo-notification::filament.devices.filters.signed_in'))
                    ->falseLabel(__('expo-notification::filament.devices.filters.guest'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull((new PushToken)->owner()->getForeignKeyName()),
                        false: fn (Builder $query) => $query->whereNull((new PushToken)->owner()->getForeignKeyName()),
                    ),
            ])
            ->groups([self::ownerGroup()])
            ->defaultGroup(ExpoNotificationPlugin::get()->isGroupingDevicesByOwner() ? 'owner' : null)
            ->defaultSort('last_registered_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    SendNotificationAction::toDevice(),
                    DeactivateDeviceAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SendNotificationAction::toDevices(),
                    DeactivateDeviceAction::bulk(),
                ]),
            ]);
    }

    /**
     * Groups devices by who owns them, guests together, keyed by {@see OwnerKey}. The header describes the owner's
     * devices ({@see OwnerSummary}) with a link to their analysis page.
     */
    private static function ownerGroup(): Group
    {
        $columns = OwnerKey::columns();
        /** @var array<string, OwnerSummary> one summary query per group per render */
        $summaries = [];

        return Group::make('owner')
            ->label(__('expo-notification::filament.devices.groups.owner'))
            ->getKeyFromRecordUsing(fn (PushToken $record): ?string => OwnerKey::fromDevice($record))
            ->getTitleFromRecordUsing(fn (PushToken $record): string => Display::owner($record->owner))
            ->getDescriptionFromRecordUsing(function (PushToken $record) use (&$summaries): Htmlable {
                $key = OwnerKey::fromDevice($record);

                return ($summaries[$key ?? ''] ??= OwnerSummary::forKey($key))->description();
            })
            ->orderQueryUsing(function (Builder $query, string $direction) use ($columns): Builder {
                foreach ($columns as $column) {
                    $query->orderBy($query->qualifyColumn($column), $direction);
                }

                return $query;
            })
            // Only used by group summaries. Filament keys those by the first group column, so a summarizer on this
            // table would need a single combined key expression in morph mode.
            ->groupQueryUsing(fn (QueryBuilder $query): QueryBuilder => $query->groupBy($columns))
            ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => OwnerKey::scope($query, $key))
            ->collapsible();
    }
}
