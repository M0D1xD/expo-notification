<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One registered device and its current Expo push token.
 *
 * @property int $id
 * @property string $device_id
 * @property string $push_token
 * @property string $platform
 * @property string|null $locale
 * @property bool $active
 * @property Carbon|null $last_registered_at
 * @property Carbon|null $deactivated_at
 */
class PushToken extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('expo-notification.tables.push_tokens');
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_registered_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /** The owner set by `owner.mode`: any model in "morph" mode, `owner.model` in "foreign" mode. */
    public function owner(): MorphTo|BelongsTo
    {
        $owner = config('expo-notification.owner');

        return $owner['mode'] === 'foreign'
            ? $this->belongsTo($owner['model'], $owner['column'])
            : $this->morphTo();
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /** @param  Builder<self>  $query */
    public function scopeForDevice(Builder $query, string $deviceId, string $pushToken): void
    {
        $query->where('device_id', $deviceId)->where('push_token', $pushToken);
    }
}
