<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

final class CouldNotGetReceipts extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        return new self(
            "Expo Push Service responded with HTTP {$response->status()}: {$response->body()}",
            $response->status(),
        );
    }
}
