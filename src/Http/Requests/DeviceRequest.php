<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use M0d1xd\ExpoNotification\ExpoPushToken;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushTokenRegistry;

/** Every device request carries the device_id + push_token pair. */
class DeviceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:255'],
            'push_token' => ['required', 'string', ExpoPushToken::rule()],
        ];
    }

    /** The stored row for the pair; a 404 when there is none, whether the device or the token is wrong. */
    public function pushToken(): PushToken
    {
        return $this->container->make(PushTokenRegistry::class)
            ->find($this->validated('device_id'), $this->validated('push_token'));
    }
}
