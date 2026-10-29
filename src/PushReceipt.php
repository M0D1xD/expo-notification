<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

/**
 * Whether the Expo Push Service handed a message to FCM/APNs. An `ok` receipt does not mean the
 * device received it: a force-stopped Android app still yields `ok` (see the milestone 3a spike).
 */
final readonly class PushReceipt
{
    /** @param  array<string, mixed>  $details */
    public function __construct(
        public string $id,
        public string $status,
        public ?ExpoErrorType $errorType = null,
        public ?string $message = null,
        public array $details = [],
    ) {}

    /** @param  array<string, mixed>  $receipt */
    public static function fromApi(string $id, array $receipt): self
    {
        $isError = ($receipt['status'] ?? null) !== 'ok';

        return new self(
            id: $id,
            status: $isError ? 'error' : 'ok',
            errorType: $isError ? ExpoErrorType::fromApi($receipt['details']['error'] ?? null) : null,
            message: $receipt['message'] ?? null,
            details: $receipt['details'] ?? [],
        );
    }

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }
}
