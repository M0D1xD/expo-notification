<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Foundation\Auth\User as GenericUser;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\Tests\DatabaseTestCase;
use M0d1xd\ExpoNotification\Tests\Fixtures\User;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;

final class PushTokenRegistrationTest extends DatabaseTestCase
{
    private const array DEVICE = ['device_id' => 'android-id-1', 'push_token' => 'ExponentPushToken[first]'];

    /** @param  array<string, mixed>  $overrides */
    private function registerDevice(array $overrides = []): TestResponse
    {
        return $this->putJson('expo-notification/push-tokens', [...self::DEVICE, 'platform' => 'android', 'locale' => 'en-US', ...$overrides]);
    }

    public function test_register_creates_then_refreshes_the_device(): void
    {
        $this->registerDevice()
            ->assertCreated()
            ->assertJsonPath('data.device_id', 'android-id-1')
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.active', true)
            ->assertJsonMissingPath('data.push_token');

        $this->registerDevice(['locale' => 'ar'])->assertOk()->assertJsonPath('data.locale', 'ar');

        $this->assertSame(1, PushToken::query()->count());
    }

    public function test_register_assigns_the_signed_in_user(): void
    {
        $user = User::make();

        $this->actingAs($user)->registerDevice()->assertCreated();

        $this->assertSame([$user->getKey()], $user->pushTokens()->pluck('owner_id')->all());
    }

    public function test_register_uses_the_configured_guard(): void
    {
        config(['auth.guards.mobile' => ['driver' => 'session', 'provider' => 'users'], 'expo-notification.routes.guard' => 'mobile']);
        $user = User::make();

        $this->actingAs($user, 'web')->registerDevice()->assertCreated();
        $this->assertNull(PushToken::query()->value('owner_id'));

        $this->actingAs($user, 'mobile')->registerDevice()->assertOk();
        $this->assertSame($user->getKey(), PushToken::query()->value('owner_id'));
    }

    #[DefineEnvironment('usesForeignOwner')]
    public function test_foreign_mode_treats_a_user_of_another_model_as_a_guest(): void
    {
        $this->actingAs((new GenericUser)->forceFill(['id' => 9]))->registerDevice()->assertCreated();

        $this->assertNull(PushToken::query()->value('user_id'));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidRegistrations(): iterable
    {
        yield 'bad token' => [['push_token' => 'not-a-token'], 'push_token'];
        yield 'bad previous token' => [['previous_push_token' => 'nope'], 'previous_push_token'];
        yield 'missing device_id' => [['device_id' => null], 'device_id'];
        yield 'unknown platform' => [['platform' => 'web'], 'platform'];
        yield 'long locale' => [['locale' => str_repeat('a', 36)], 'locale'];
    }

    /** @param  array<string, mixed>  $overrides */
    #[DataProvider('invalidRegistrations')]
    public function test_register_validates_the_payload(array $overrides, string $field): void
    {
        $this->registerDevice($overrides)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_detach_clears_the_owner(): void
    {
        $this->actingAs(User::make())->registerDevice();

        $this->postJson('expo-notification/push-tokens/detach', self::DEVICE)->assertNoContent();

        $this->assertNull(PushToken::query()->value('owner_id'));
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function mismatchedPairs(): iterable
    {
        yield 'wrong token' => [['push_token' => 'ExponentPushToken[other]']];
        yield 'unknown device' => [['device_id' => 'someone-else']];
    }

    /** @param  array<string, string>  $overrides */
    #[DataProvider('mismatchedPairs')]
    public function test_detach_and_deactivate_require_the_stored_pair(array $overrides): void
    {
        $this->actingAs(User::make())->registerDevice();
        $pair = [...self::DEVICE, ...$overrides];

        $this->postJson('expo-notification/push-tokens/detach', $pair)->assertNotFound();
        $this->deleteJson('expo-notification/push-tokens', $pair)->assertNotFound();

        $this->assertNotNull(PushToken::query()->value('owner_id'));
        $this->assertTrue(PushToken::query()->value('active'));
    }

    public function test_delete_deactivates_the_device(): void
    {
        $this->registerDevice();

        $this->deleteJson('expo-notification/push-tokens', self::DEVICE)->assertNoContent();

        $this->assertFalse(PushToken::query()->sole()->active);
    }

    public function test_routes_are_throttled(): void
    {
        $this->assertContains('throttle:60,1', Route::getRoutes()->getByName('expo-notification.push-tokens.register')->gatherMiddleware());
    }

    #[DefineEnvironment('disablesRoutes')]
    public function test_routes_can_be_disabled(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('expo-notification.push-tokens.register'));
    }

    protected function disablesRoutes($app): void
    {
        $app['config']->set('expo-notification.routes.enabled', false);
    }
}
