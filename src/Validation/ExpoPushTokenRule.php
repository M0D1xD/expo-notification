<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use M0d1xd\ExpoNotification\ExpoPushToken;

final class ExpoPushTokenRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ExpoPushToken::isValid($value)) {
            $fail('The :attribute must be a valid Expo push token.');
        }
    }
}
