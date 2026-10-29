<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Http\Requests;

final class RecordEventsRequest extends DeviceRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.notification_id' => ['required', 'string', 'max:64'],
            'events.*.type' => ['required', 'string', 'in:received,opened,dismissed'],
            // Which client code path saw it (listener, task, cold-start); free-form so newer clients still pass.
            'events.*.source' => ['nullable', 'string', 'max:16'],
            'events.*.app_state' => ['nullable', 'string', 'max:16'],
            'events.*.occurred_at' => ['required', 'date'],
        ];
    }
}
