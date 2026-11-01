<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use M0d1xd\ExpoNotification\Models\PushToken;

/**
 * Identifies a device's owner by its columns: "type:id" in morph mode, the foreign key otherwise; null for guests.
 * Two owners with the same name have different keys.
 */
final class OwnerKey
{
    /** @return list<string> the owner columns on the push tokens table */
    public static function columns(): array
    {
        $owner = (new PushToken)->owner();

        return $owner instanceof MorphTo
            ? [$owner->getMorphType(), $owner->getForeignKeyName()]
            : [$owner->getForeignKeyName()];
    }

    public static function fromDevice(PushToken $device): ?string
    {
        $values = array_map(fn (string $column) => $device->getAttribute($column), self::columns());

        return in_array(null, $values, true) ? null : implode(':', $values);
    }

    /**
     * Limits a push tokens query to one owner's devices; a null key means guest devices, a malformed key matches none.
     *
     * @param  Builder<PushToken>  $query
     * @return Builder<PushToken>
     */
    public static function scope(Builder $query, ?string $key): Builder
    {
        $columns = self::columns();

        if ($key === null) {
            return $query->whereNull($query->qualifyColumn($columns[0]));
        }

        $values = self::split($key, count($columns));

        if ($values === null) {
            return $query->whereRaw('1 = 0');
        }

        foreach (array_combine($columns, $values) as $column => $value) {
            $query->where($query->qualifyColumn($column), $value);
        }

        return $query;
    }

    /** URL-safe form of a key (morph types contain "\"). */
    public static function encode(string $key): string
    {
        return rtrim(strtr(base64_encode($key), '+/', '-_'), '=');
    }

    /** The key back from {@see encode()}, or null when it is not a valid key for the current owner mode. */
    public static function decode(string $encoded): ?string
    {
        $key = base64_decode(strtr($encoded, '-_', '+/'), true);

        return $key === false || $key === '' || self::split($key, count(self::columns())) === null ? null : $key;
    }

    /** @return list<string>|null split on the last ":" (ids never contain one; a morph alias might) */
    private static function split(string $key, int $parts): ?array
    {
        if ($parts === 1) {
            return [$key];
        }

        $separator = strrpos($key, ':');

        return $separator === false || $separator === 0 || $separator === strlen($key) - 1
            ? null
            : [substr($key, 0, $separator), substr($key, $separator + 1)];
    }
}
