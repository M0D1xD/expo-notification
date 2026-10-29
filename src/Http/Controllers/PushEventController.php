<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Http\Controllers;

use Illuminate\Http\JsonResponse;
use M0d1xd\ExpoNotification\EventRecorder;
use M0d1xd\ExpoNotification\Http\Requests\RecordEventsRequest;

final class PushEventController
{
    public function __construct(private readonly EventRecorder $recorder) {}

    /** Always 200 for a valid batch, so the client clears its queue even when some events were ignored. */
    public function store(RecordEventsRequest $request): JsonResponse
    {
        $result = $this->recorder->record($request->pushToken(), $request->validated('events'));

        return new JsonResponse(['stored' => $result->stored, 'duplicates' => $result->duplicates, 'ignored' => $result->ignored]);
    }
}
