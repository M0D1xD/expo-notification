<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use M0d1xd\ExpoNotification\PushNotifier;

/**
 * A notification sent through {@see PushNotifier}. Its id reaches devices as `data.notificationId`.
 *
 * @property string $id
 * @property string|null $title
 * @property string|null $body
 * @property array<string, mixed>|null $data
 * @property array<string, mixed> $payload
 * @property Carbon|null $sent_at
 */
class PushNotification extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('expo-notification.tables.notifications');
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PushNotificationDelivery::class, 'push_notification_id');
    }
}
