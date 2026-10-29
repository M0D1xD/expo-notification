<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use M0d1xd\ExpoNotification\Concerns\HasPushTokens;

final class User extends Authenticatable
{
    use HasPushTokens;

    protected $table = 'users';

    protected $guarded = [];

    public static function make(string $name = 'Ali'): self
    {
        return self::query()->create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'secret']);
    }
}
