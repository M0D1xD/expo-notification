<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

/** The Expo Push Service's answer for one message: accepted (with a receipt id) or rejected. */
final readonly class PushTicket
{
    /** @param  array<string, mixed>  $details */
    public function __construct(
        public string $token,
        public string $status,
        public ?string $id = null,
        public ?ExpoErrorType $errorType = null,
        public ?string $message = null,
        public array $details = [],
    ) {}

    /** @param  array<string, mixed>  $ticket */
    public static function fromApi(string $token, array $ticket): self
    {
        $isError = ($ticket['status'] ?? null) !== 'ok';

        return new self(
            token: $token,
            status: $isError ? 'error' : 'ok',
            id: $ticket['id'] ?? null,
            errorType: $isError ? ExpoErrorType::fromApi($ticket['details']['error'] ?? null) : null,
            message: $ticket['message'] ?? null,
            details: $ticket['details'] ?? [],
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
