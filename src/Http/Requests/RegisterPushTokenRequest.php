<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Http\Requests;

use M0d1xd\ExpoNotification\ExpoPushToken;

final class RegisterPushTokenRequest extends DeviceRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'platform' => ['required', 'string', 'in:ios,android'],
            'locale' => ['nullable', 'string', 'max:35'],
            // The token this device had before, to prove a rotation and keep the owner.
            'previous_push_token' => ['nullable', 'string', ExpoPushToken::rule()],
        ];
    }
}
