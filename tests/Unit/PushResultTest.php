<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Unit;

use M0d1xd\ExpoNotification\ExpoErrorType;
use M0d1xd\ExpoNotification\PushReceipt;
use M0d1xd\ExpoNotification\PushTicket;
use PHPUnit\Framework\TestCase;

final class PushResultTest extends TestCase
{
    public function test_an_ok_ticket(): void
    {
        $ticket = PushTicket::fromApi('ExpoPushToken[a]', ['status' => 'ok', 'id' => 'ticket-1']);

        $this->assertTrue($ticket->isOk());
        $this->assertSame('ticket-1', $ticket->id);
        $this->assertSame('ExpoPushToken[a]', $ticket->token);
        $this->assertNull($ticket->errorType);
    }

    public function test_an_error_ticket(): void
    {
        $ticket = PushTicket::fromApi('ExpoPushToken[a]', [
            'status' => 'error',
            'message' => '"ExpoPushToken[a]" is not a registered push notification recipient',
            'details' => ['error' => 'DeviceNotRegistered'],
        ]);

        $this->assertTrue($ticket->isError());
        $this->assertNull($ticket->id);
        $this->assertTrue($ticket->errorType->isDeviceNotRegistered());
        $this->assertStringContainsString('not a registered', $ticket->message);
        $this->assertSame(['error' => 'DeviceNotRegistered'], $ticket->details);
    }

    public function test_an_unlisted_error_becomes_unknown_instead_of_throwing(): void
    {
        $ticket = PushTicket::fromApi('ExpoPushToken[a]', ['status' => 'error', 'details' => ['error' => 'SomethingNew']]);

        $this->assertSame(ExpoErrorType::Unknown, $ticket->errorType);
    }

    public function test_an_error_without_details_is_unknown(): void
    {
        $this->assertSame(ExpoErrorType::Unknown, PushTicket::fromApi('t', ['status' => 'error'])->errorType);
    }

    public function test_the_error_type_covers_all_documented_errors(): void
    {
        foreach (['DeviceNotRegistered', 'MessageTooBig', 'MessageRateExceeded', 'MismatchSenderId', 'InvalidCredentials',
            'InvalidProviderToken', 'DeveloperError', 'ExpoError', 'ProviderError'] as $error) {
            $this->assertNotSame(ExpoErrorType::Unknown, ExpoErrorType::fromApi($error), $error);
        }
    }

    public function test_a_receipt(): void
    {
        $ok = PushReceipt::fromApi('ticket-1', ['status' => 'ok']);
        $error = PushReceipt::fromApi('ticket-2', ['status' => 'error', 'details' => ['error' => 'MessageRateExceeded']]);

        $this->assertTrue($ok->isOk());
        $this->assertSame('ticket-1', $ok->id);
        $this->assertTrue($error->isError());
        $this->assertTrue($error->errorType->isMessageRateExceeded());
    }
}
