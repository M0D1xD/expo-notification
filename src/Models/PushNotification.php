<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use M0d1xd\ExpoNotification\DeliveryStats;
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
 * @property int|null $deliveries_count with withDeliveryStats(), like the *_count attributes below
 * @property int|null $accepted_count
 * @property int|null $failed_count
 * @property int|null $delivered_count
 * @property int|null $opened_count
 * @property int|null $dismissed_count
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

    public function events(): HasMany
    {
        return $this->hasMany(PushNotificationEvent::class, 'push_notification_id');
    }

    /**
     * Adds per-notification counts in one query: deliveries_count (targeted devices), accepted_count, failed_count,
     * delivered_count, opened_count and dismissed_count. Read them as a whole with {@see DeliveryStats::fromCounts()}.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithDeliveryStats(Builder $query): void
    {
        $query->withCount([
            'deliveries',
            'deliveries as accepted_count' => fn (Builder $q) => $q->where('ticket_status', 'ok'),
            'deliveries as failed_count' => fn (Builder $q) => $q->failed(),
            'deliveries as delivered_count' => fn (Builder $q) => $q->delivered(),
            'deliveries as opened_count' => fn (Builder $q) => $q->opened(),
            'deliveries as dismissed_count' => fn (Builder $q) => $q->dismissed(),
        ]);
    }

    public function stats(): DeliveryStats
    {
        return isset($this->deliveries_count)
            ? DeliveryStats::fromCounts($this)
            : DeliveryStats::for($this->deliveries()->getQuery());
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PushNotificationDelivery::class, 'push_notification_id');
    }
}
