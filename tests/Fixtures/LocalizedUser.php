<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Fixtures;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use M0d1xd\ExpoNotification\Concerns\HasPushTokens;

/** A device owner with a saved language, read through Laravel's HasLocalePreference. */
final class LocalizedUser extends Model implements HasLocalePreference
{
    use HasPushTokens;

    protected $table = 'users';

    protected $guarded = [];

    public static function make(?string $locale, string $name = 'Sara'): self
    {
        if (! Schema::hasColumn('users', 'preferred_language')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('preferred_language')->nullable());
        }

        return self::query()->create([
            'name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'secret', 'preferred_language' => $locale,
        ]);
    }

    public function preferredLocale(): ?string
    {
        return $this->preferred_language;
    }
}
