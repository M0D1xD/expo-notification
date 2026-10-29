<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use Illuminate\Database\Eloquent\Collection;
use M0d1xd\ExpoNotification\Exceptions\CouldNotGetReceipts;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;

/** Fetches Expo receipts for accepted deliveries and records them. */
final class ReceiptChecker
{
    /** Expo accepts at most 1000 ticket ids per receipts request. */
    private const int CHUNK_SIZE = 1000;

    public function __construct(private readonly ExpoClient $client, private readonly DeliveryFailureHandler $failures) {}

    /**
     * Check every delivery sent at least `$minAge` minutes ago (default `receipts.delay`) that has no receipt yet.
     * Ids Expo does not return stay pending until `receipts.expire_after` hours, then become `expired`.
     *
     * @throws CouldNotGetReceipts
     */
    public function check(?int $minAge = null): ReceiptCheckResult
    {
        $sentBefore = now()->subMinutes($minAge ?? (int) config('expo-notification.receipts.delay', 15));
        $expireBefore = now()->subHours((int) config('expo-notification.receipts.expire_after', 24));
        $counts = ['ok' => 0, 'errors' => 0, 'pending' => 0, 'expired' => 0];

        PushNotificationDelivery::query()
            ->pendingReceipt($sentBefore)
            ->chunkById(self::CHUNK_SIZE, function (Collection $deliveries) use ($expireBefore, &$counts) {
                $receipts = $this->client->receipts($deliveries->pluck('ticket_id'));

                foreach ($deliveries as $delivery) {
                    $receipt = $receipts->get($delivery->ticket_id);

                    if ($receipt === null) {
                        $expired = $delivery->sent_at->lessThan($expireBefore);
                        $delivery->forceFill(['receipt_status' => $expired ? 'expired' : null, 'receipt_checked_at' => now()])->save();
                        $counts[$expired ? 'expired' : 'pending']++;

                        continue;
                    }

                    $delivery->forceFill([
                        'receipt_status' => $receipt->status,
                        'error_type' => $receipt->errorType?->value,
                        'error_message' => $receipt->message,
                        'details' => $receipt->details ?: null,
                        'receipt_checked_at' => now(),
                    ])->save();

                    if ($receipt->isError()) {
                        $this->failures->handle($delivery);
                    }
                    $counts[$receipt->isOk() ? 'ok' : 'errors']++;
                }
            });

        return new ReceiptCheckResult(...$counts);
    }
}
