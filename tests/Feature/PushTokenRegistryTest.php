<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushTokenRegistry;
use M0d1xd\ExpoNotification\Tests\DatabaseTestCase;
use M0d1xd\ExpoNotification\Tests\Fixtures\User;
use Orchestra\Testbench\Attributes\DefineEnvironment;

final class PushTokenRegistryTest extends DatabaseTestCase
{
    private const string DEVICE = 'android-id-1';

    private const string TOKEN = 'ExponentPushToken[first]';

    private PushTokenRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->app->make(PushTokenRegistry::class);
    }

    /** @param  array<string, mixed>  $overrides */
    private function register(array $overrides = [], ?Model $owner = null): PushToken
    {
        return $this->registry->register(
            [...['device_id' => self::DEVICE, 'push_token' => self::TOKEN, 'platform' => 'android', 'locale' => 'en-US'], ...$overrides],
            $owner,
        );
    }

    public function test_it_creates_a_guest_device(): void
    {
        $token = $this->register();

        $this->assertTrue($token->wasRecentlyCreated);
        $this->assertSame(['android-id-1', 'ExponentPushToken[first]', 'android', 'en-US', true], [
            $token->device_id, $token->push_token, $token->platform, $token->locale, $token->active,
        ]);
        $this->assertNull($token->owner_id);
        $this->assertNotNull($token->last_registered_at);
    }

    public function test_refreshing_the_same_device_updates_the_row_without_duplicating_it(): void
    {
        $this->register();
        $this->travel(5)->minutes();

        $token = $this->register(['locale' => 'ar']);

        $this->assertFalse($token->wasRecentlyCreated);
        $this->assertSame(1, PushToken::query()->count());
        $this->assertSame('ar', $token->locale);
        $this->assertTrue($token->last_registered_at->isAfter(now()->subMinute()));
    }

    public function test_a_missing_locale_keeps_the_stored_one(): void
    {
        $this->register();

        $this->assertSame('en-US', $this->register(['locale' => null])->locale);
    }

    public function test_an_authenticated_register_assigns_the_owner(): void
    {
        $this->register();
        $user = User::make();

        $this->assertTrue($this->register(owner: $user)->owner->is($user));
    }

    public function test_a_guest_refresh_with_the_same_token_keeps_the_owner(): void
    {
        $user = User::make();
        $this->register(owner: $user);

        $this->assertTrue($this->register()->owner->is($user));
    }

    public function test_a_proven_rotation_keeps_the_owner(): void
    {
        $user = User::make();
        $this->register(owner: $user);

        $token = $this->register(['push_token' => 'ExponentPushToken[second]', 'previous_push_token' => self::TOKEN]);

        $this->assertSame('ExponentPushToken[second]', $token->push_token);
        $this->assertTrue($token->owner->is($user));
    }

    public function test_an_unproven_rotation_clears_the_owner(): void
    {
        $this->register(owner: User::make());

        $token = $this->register(['push_token' => 'ExponentPushToken[second]', 'previous_push_token' => 'ExponentPushToken[guess]']);

        $this->assertSame('ExponentPushToken[second]', $token->push_token);
        $this->assertNull($token->owner_id);
        $this->assertNull($token->owner_type);
    }

    public function test_an_unproven_rotation_does_not_keep_the_previous_locale(): void
    {
        $this->register(['locale' => 'ar']);

        // Someone who only knows the device_id must not read back what the device stored.
        $token = $this->register(['push_token' => 'ExponentPushToken[other]', 'locale' => null]);

        $this->assertNull($token->locale);
    }

    public function test_a_token_seen_on_another_device_moves_to_it(): void
    {
        $this->register(['device_id' => 'old-device']);

        $token = $this->register(['device_id' => 'new-device']);

        $this->assertSame(['new-device'], PushToken::query()->pluck('device_id')->all());
        $this->assertSame(self::TOKEN, $token->push_token);
    }

    public function test_registering_again_reactivates_a_deactivated_device(): void
    {
        $this->registry->deactivate($this->register());

        $token = $this->register();

        $this->assertTrue($token->active);
        $this->assertNull($token->deactivated_at);
    }

    public function test_detach_clears_the_owner_and_keeps_the_device(): void
    {
        $this->register(owner: User::make());

        $token = $this->registry->detach($this->registry->find(self::DEVICE, self::TOKEN));

        $this->assertNull($token->fresh()->owner_id);
        $this->assertTrue($token->fresh()->active);
    }

    public function test_deactivate_marks_the_device_inactive(): void
    {
        $token = $this->registry->deactivate($this->register())->fresh();

        $this->assertFalse($token->active);
        $this->assertNotNull($token->deactivated_at);
    }

    public function test_find_requires_both_device_id_and_token_to_match(): void
    {
        $this->register();

        $this->expectException(ModelNotFoundException::class);

        $this->registry->find(self::DEVICE, 'ExponentPushToken[other]');
    }

    #[DefineEnvironment('usesForeignOwner')]
    public function test_foreign_mode_rejects_an_owner_of_another_model(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->register(owner: new PushToken);
    }
}
