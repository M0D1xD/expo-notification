<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

/**
 * Errors reported by the Expo Push Service in push tickets and receipts.
 *
 * @see https://docs.expo.dev/push-notifications/sending-notifications/#individual-errors
 */
enum ExpoErrorType: string
{
    /** The device can no longer receive pushes; stop sending to the token. */
    case DeviceNotRegistered = 'DeviceNotRegistered';

    /** The payload exceeds the 4096-byte limit. */
    case MessageTooBig = 'MessageTooBig';

    /** Too many messages to the same device; back off and retry. */
    case MessageRateExceeded = 'MessageRateExceeded';

    /** The FCM credentials do not match the app's sender ID. */
    case MismatchSenderId = 'MismatchSenderId';

    /** The push credentials for the app are invalid or revoked. */
    case InvalidCredentials = 'InvalidCredentials';

    /** The provider (FCM/APNs) rejected the device token. */
    case InvalidProviderToken = 'InvalidProviderToken';

    case DeveloperError = 'DeveloperError';

    case ExpoError = 'ExpoError';

    case ProviderError = 'ProviderError';

    /** Any error not listed above, so new Expo error codes never break sending. */
    case Unknown = 'Unknown';

    public static function fromApi(?string $error): self
    {
        return $error === null ? self::Unknown : (self::tryFrom($error) ?? self::Unknown);
    }

    public function isDeviceNotRegistered(): bool
    {
        return $this === self::DeviceNotRegistered;
    }

    public function isMessageTooBig(): bool
    {
        return $this === self::MessageTooBig;
    }

    public function isMessageRateExceeded(): bool
    {
        return $this === self::MessageRateExceeded;
    }

    public function isMismatchSenderId(): bool
    {
        return $this === self::MismatchSenderId;
    }

    public function isInvalidCredentials(): bool
    {
        return $this === self::InvalidCredentials;
    }

    public function isUnknown(): bool
    {
        return $this === self::Unknown;
    }
}
