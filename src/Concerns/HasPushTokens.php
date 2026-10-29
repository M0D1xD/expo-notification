<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use M0d1xd\ExpoNotification\Models\PushToken;

/** For the model that owns devices, usually the User. */
trait HasPushTokens
{
    public function pushTokens(): MorphMany|HasMany
    {
        $owner = config('expo-notification.owner');

        return $owner['mode'] === 'foreign'
            ? $this->hasMany(PushToken::class, $owner['column'])
            : $this->morphMany(PushToken::class, 'owner');
    }

    /**
     * The owner's active tokens, ready for `ExpoNotification::send()`.
     *
     * @return Collection<int, string>
     */
    public function expoPushTokens(): Collection
    {
        return $this->pushTokens()->active()->pluck('push_token');
    }
}
