<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One notification sent to one device: the Expo ticket, then the receipt.
 *
 * ticket_status: ok | error | failed (the request to Expo failed).
 * receipt_status: ok | error | expired (Expo no longer has it); a receipt `ok` means handed to FCM/APNs, not delivered.
 *
 * @property int $id
 * @property string $push_notification_id
 * @property int|null $push_token_id
 * @property string $push_token
 * @property string|null $ticket_id
 * @property string|null $ticket_status
 * @property string|null $receipt_status
 * @property string|null $error_type
 * @property string|null $error_message
 * @property array<string, mixed>|null $details
 * @property Carbon|null $sent_at
 * @property Carbon|null $receipt_checked_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $dismissed_at
 */
class PushNotificationDelivery extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('expo-notification.tables.deliveries');
    }

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'sent_at' => 'datetime',
            'receipt_checked_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'dismissed_at' => 'datetime',
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

    /**
     * Accepted by Expo, sent before the given time, and still without a receipt.
     *
     * @param  Builder<self>  $query
     */
    public function scopePendingReceipt(Builder $query, DateTimeInterface $sentBefore): void
    {
        $query->where('ticket_status', 'ok')->whereNull('receipt_status')->where('sent_at', '<=', $sentBefore);
    }

    /**
     * Rejected by Expo (ticket `error`), never reached Expo (ticket `failed`) or rejected by FCM/APNs (receipt `error`).
     *
     * @param  Builder<self>  $query
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereIn('ticket_status', ['error', 'failed'])->orWhere('receipt_status', 'error'));
    }

    /**
     * The device reported it (received, opened or dismissed). A receipt `ok` alone does not count.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDelivered(Builder $query): void
    {
        $query->whereNotNull('delivered_at');
    }

    /** @param  Builder<self>  $query */
    public function scopeOpened(Builder $query): void
    {
        $query->whereNotNull('opened_at');
    }

    /** @param  Builder<self>  $query */
    public function scopeDismissed(Builder $query): void
    {
        $query->whereNotNull('dismissed_at');
    }

    public function isFailed(): bool
    {
        return in_array($this->ticket_status, ['error', 'failed'], true) || $this->receipt_status === 'error';
    }
}
