<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

/** Counts from one receipt check. */
final readonly class ReceiptCheckResult
{
    public function __construct(
        public int $ok = 0,
        public int $errors = 0,
        public int $pending = 0,
        public int $expired = 0,
    ) {}
}
