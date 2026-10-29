<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\Tests\DatabaseTestCase;
use M0d1xd\ExpoNotification\Tests\Fixtures\User;
use Orchestra\Testbench\Attributes\DefineEnvironment;

final class HasPushTokensTest extends DatabaseTestCase
{
    private function registerDevices(User $user): void
    {
        $registry = $this->app->make(PushTokenRegistry::class);
        $registry->register(['device_id' => 'phone', 'push_token' => 'ExpoPushToken[phone]', 'platform' => 'android'], $user);
        $registry->register(['device_id' => 'tablet', 'push_token' => 'ExpoPushToken[tablet]', 'platform' => 'ios'], $user);
        $registry->register(['device_id' => 'old', 'push_token' => 'ExpoPushToken[old]', 'platform' => 'ios'], $user);
        $registry->deactivate(PushToken::query()->where('device_id', 'old')->sole());
        $registry->register(['device_id' => 'guest', 'push_token' => 'ExpoPushToken[guest]', 'platform' => 'ios']);
    }

    public function test_morph_owner_has_many_devices_and_only_active_tokens_are_sendable(): void
    {
        $user = User::make();
        $this->registerDevices($user);

        $this->assertCount(3, $user->pushTokens);
        $this->assertSame(['ExpoPushToken[phone]', 'ExpoPushToken[tablet]'], $user->expoPushTokens()->sort()->values()->all());
        $this->assertTrue(PushToken::query()->where('device_id', 'phone')->sole()->owner->is($user));
        $this->assertSame(User::class, PushToken::query()->where('device_id', 'phone')->value('owner_type'));
    }

    #[DefineEnvironment('usesForeignOwner')]
    public function test_foreign_owner_uses_the_configured_column(): void
    {
        $user = User::make();
        $this->registerDevices($user);

        $this->assertSame(['ExpoPushToken[phone]', 'ExpoPushToken[tablet]'], $user->expoPushTokens()->sort()->values()->all());
        $this->assertSame($user->id, PushToken::query()->where('device_id', 'phone')->value('user_id'));
        $this->assertTrue(PushToken::query()->where('device_id', 'phone')->sole()->owner->is($user));
    }
}
