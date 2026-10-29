<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use M0d1xd\ExpoNotification\Http\Requests\DeviceRequest;
use M0d1xd\ExpoNotification\Http\Requests\RegisterPushTokenRequest;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushTokenRegistry;

final class PushTokenController
{
    public function __construct(private readonly PushTokenRegistry $registry, private readonly Auth $auth) {}

    public function register(RegisterPushTokenRequest $request): JsonResponse
    {
        $token = $this->registry->register($request->validated(), $this->owner());

        return new JsonResponse(['data' => $this->present($token)], $token->wasRecentlyCreated ? 201 : 200);
    }

    public function detach(DeviceRequest $request): Response
    {
        $this->registry->detach($request->pushToken());

        return new Response(status: 204);
    }

    public function destroy(DeviceRequest $request): Response
    {
        $this->registry->deactivate($request->pushToken());

        return new Response(status: 204);
    }

    /** The signed-in user on the configured guard, or null for guests (the routes have no auth middleware). */
    private function owner(): ?Model
    {
        $user = $this->auth->guard(config('expo-notification.routes.guard'))->user();

        return $user instanceof Model && $this->registry->acceptsOwner($user) ? $user : null;
    }

    /** @return array<string, mixed> */
    private function present(PushToken $token): array
    {
        return [
            'device_id' => $token->device_id,
            'platform' => $token->platform,
            'locale' => $token->locale,
            'active' => $token->active,
            'last_registered_at' => $token->last_registered_at?->toIso8601String(),
        ];
    }
}
