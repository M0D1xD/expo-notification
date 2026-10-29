<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use M0d1xd\ExpoNotification\Models\PushToken;

/** Registers, detaches and deactivates devices. One row per device_id, one push token per row. */
final class PushTokenRegistry
{
    /**
     * Create or refresh the device's row.
     *
     * The owner is set when given. Without one it is kept, unless the request cannot prove it holds
     * the device (the token changed and `previous_push_token` does not match): then it is cleared,
     * so knowing a device_id alone never inherits someone else's notifications.
     *
     * @param  array{device_id: string, push_token: string, platform: string, locale?: ?string, previous_push_token?: ?string}  $data
     */
    public function register(array $data, ?Model $owner = null): PushToken
    {
        if ($owner !== null && ! $this->acceptsOwner($owner)) {
            throw new InvalidArgumentException('The owner must be an instance of '.config('expo-notification.owner.model').'.');
        }

        // Two first registrations of the same device can race on the unique device_id; the retry finds the winner's row.
        return retry(
            2,
            fn () => (new PushToken)->getConnection()->transaction(fn () => $this->upsert($data, $owner)),
            0,
            fn ($e) => $e instanceof UniqueConstraintViolationException,
        );
    }

    /**
     * The row matching both device_id and push_token.
     *
     * @throws ModelNotFoundException<PushToken>
     */
    public function find(string $deviceId, string $pushToken): PushToken
    {
        return PushToken::query()->forDevice($deviceId, $pushToken)->firstOrFail();
    }

    /** Logout: the device stays registered as a guest. */
    public function detach(PushToken $token): PushToken
    {
        $token->owner()->dissociate()->save();

        return $token;
    }

    /** Opt-out: no more pushes until the device registers again. */
    public function deactivate(PushToken $token): PushToken
    {
        $token->forceFill(['active' => false, 'deactivated_at' => now()])->save();

        return $token;
    }

    public function acceptsOwner(Model $owner): bool
    {
        return config('expo-notification.owner.mode') !== 'foreign'
            || $owner instanceof (config('expo-notification.owner.model'));
    }

    /** @param  array{device_id: string, push_token: string, platform: string, locale?: ?string, previous_push_token?: ?string}  $data */
    private function upsert(array $data, ?Model $owner): PushToken
    {
        $token = PushToken::query()->where('device_id', $data['device_id'])->lockForUpdate()->first()
            ?? new PushToken(['device_id' => $data['device_id']]);

        $proven = ! $token->exists
            || in_array($token->push_token, [$data['push_token'], $data['previous_push_token'] ?? null], true);

        if ($owner !== null) {
            $token->owner()->associate($owner);
        } elseif (! $proven) {
            $token->owner()->dissociate();
        }

        // A push token belongs to one device; when it shows up on another device_id, the old row goes.
        PushToken::query()
            ->where('push_token', $data['push_token'])
            ->where('device_id', '!=', $data['device_id'])
            ->delete();

        $token->fill([
            'push_token' => $data['push_token'],
            'platform' => $data['platform'],
            // Only a proven refresh keeps the stored locale: the response must not reveal it to a stranger.
            'locale' => $data['locale'] ?? ($proven ? $token->locale : null),
            'active' => true,
            'deactivated_at' => null,
            'last_registered_at' => now(),
        ])->save();

        return $token;
    }
}
