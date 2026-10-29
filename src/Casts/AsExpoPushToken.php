<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use M0d1xd\ExpoNotification\ExpoPushToken;

/** @implements CastsAttributes<ExpoPushToken|null, ExpoPushToken|string|null> */
final class AsExpoPushToken implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ExpoPushToken
    {
        return $value === null ? null : ExpoPushToken::make((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof ExpoPushToken => $value->asString(),
            default => ExpoPushToken::make((string) $value)->asString(),
        };
    }
}
