<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What a device reported about a notification: received, opened or dismissed. At most one per notification,
 * device and type.
 *
 * @property int $id
 * @property string $push_notification_id
 * @property int|null $push_token_id
 * @property string $type
 * @property string|null $source listener | task | cold-start
 * @property string|null $app_state
 * @property Carbon $occurred_at
 */
class PushNotificationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('expo-notification.tables.events');
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(PushNotification::class, 'push_notification_id');
    }

    public function pushToken(): BelongsTo
    {
        return $this->belongsTo(PushToken::class);
    }
}
