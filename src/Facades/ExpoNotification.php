<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Facades;

use Illuminate\Support\Facades\Facade;
use M0d1xd\ExpoNotification\ExpoClient;

/**
 * @method static \Illuminate\Support\Collection<int, \M0d1xd\ExpoNotification\PushTicket> send(\M0d1xd\ExpoNotification\ExpoMessage $message, iterable<\M0d1xd\ExpoNotification\ExpoPushToken|string> $tokens)
 * @method static \Illuminate\Support\Collection<string, \M0d1xd\ExpoNotification\PushReceipt> receipts(iterable<string> $ticketIds)
 *
 * @see ExpoClient
 */
final class ExpoNotification extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ExpoClient::class;
    }
}
