<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

/** Counts from one batch of device events. */
final readonly class EventRecordResult
{
    public function __construct(
        public int $stored = 0,
        public int $duplicates = 0,
        public int $ignored = 0,
    ) {}
}
