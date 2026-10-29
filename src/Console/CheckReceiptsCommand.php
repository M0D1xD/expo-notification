<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Console;

use Illuminate\Console\Command;
use M0d1xd\ExpoNotification\Exceptions\CouldNotGetReceipts;
use M0d1xd\ExpoNotification\ReceiptChecker;

/** Fetches Expo receipts for tracked deliveries; scheduled by the package on `receipts.schedule`. */
final class CheckReceiptsCommand extends Command
{
    protected $signature = 'expo-notification:check-receipts
        {--min-age= : Minutes since sending before a receipt is fetched (default: receipts.delay)}';

    protected $description = 'Fetch Expo push receipts and record them per device';

    public function handle(ReceiptChecker $checker): int
    {
        $minAge = $this->option('min-age');

        try {
            $result = $checker->check($minAge === null ? null : (int) $minAge);
        } catch (CouldNotGetReceipts $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Receipts: %d ok, %d error, %d pending, %d expired.',
            $result->ok, $result->errors, $result->pending, $result->expired,
        ));

        return self::SUCCESS;
    }
}
