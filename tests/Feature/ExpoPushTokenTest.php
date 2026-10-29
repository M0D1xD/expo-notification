<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use M0d1xd\ExpoNotification\Casts\AsExpoPushToken;
use M0d1xd\ExpoNotification\ExpoPushToken;
use M0d1xd\ExpoNotification\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ExpoPushTokenTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function tokens(): iterable
    {
        yield 'exponent prefix' => ['ExponentPushToken[1p6yUPNmaXm-MFI_S3EFSg]', true];
        yield 'expo prefix' => ['ExpoPushToken[abc123]', true];
        yield 'empty brackets' => ['ExponentPushToken[]', false];
        yield 'missing bracket' => ['ExponentPushToken[abc', false];
        yield 'raw fcm token' => ['fGx2:APA91bH-raw-device-token', false];
        yield 'empty string' => ['', false];
    }

    #[DataProvider('tokens')]
    public function test_it_validates_the_token_format(string $token, bool $valid): void
    {
        $this->assertSame($valid, ExpoPushToken::isValid($token));
        // Like any non-implicit rule, it skips empty values; presence is `required`'s job.
        $this->assertSame($valid, Validator::make(['token' => $token], ['token' => ['required', ExpoPushToken::rule()]])->passes());
    }

    public function test_make_returns_a_stringable_value_object(): void
    {
        $token = ExpoPushToken::make('ExpoPushToken[abc]');

        $this->assertSame('ExpoPushToken[abc]', (string) $token);
        $this->assertSame('"ExpoPushToken[abc]"', json_encode($token));
        $this->assertTrue($token->equals(ExpoPushToken::make('ExpoPushToken[abc]')));
    }

    public function test_make_rejects_an_invalid_token(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ExpoPushToken::make('not-a-token');
    }

    public function test_the_rule_rejects_non_strings(): void
    {
        $this->assertTrue(Validator::make(['token' => 123], ['token' => ExpoPushToken::rule()])->fails());
    }

    public function test_the_cast_round_trips_tokens_and_null(): void
    {
        $model = new class extends Model
        {
            protected $casts = ['token' => AsExpoPushToken::class];
        };

        $model->token = 'ExponentPushToken[xyz]';
        $this->assertInstanceOf(ExpoPushToken::class, $model->token);
        $this->assertSame('ExponentPushToken[xyz]', $model->getAttributes()['token']);

        $model->token = ExpoPushToken::make('ExpoPushToken[other]');
        $this->assertSame('ExpoPushToken[other]', $model->getAttributes()['token']);

        $model->token = null;
        $this->assertNull($model->token);
    }

    public function test_the_cast_rejects_an_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $model = new class extends Model
        {
            protected $casts = ['token' => AsExpoPushToken::class];
        };
        $model->token = 'nope';
    }
}
