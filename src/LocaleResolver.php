<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use Illuminate\Contracts\Translation\HasLocalePreference;
use M0d1xd\ExpoNotification\Models\PushToken;

/**
 * Picks a device's language: its owner's preferred locale (Laravel's HasLocalePreference) first, then the locale
 * the device registered with, then the fallback. "ar-SA" and "ar_EG" match "ar" when there is no exact match.
 */
final class LocaleResolver
{
    /** @param  list<string>  $available */
    public function resolve(PushToken $token, array $available, string $fallback): string
    {
        $owner = $token->owner;
        $candidates = [
            $owner instanceof HasLocalePreference ? $owner->preferredLocale() : null,
            $token->locale,
        ];

        foreach ($candidates as $candidate) {
            if (filled($candidate) && ($match = $this->match((string) $candidate, $available)) !== null) {
                return $match;
            }
        }

        return $fallback;
    }

    /** @param  list<string>  $available */
    public function match(string $locale, array $available): ?string
    {
        $normalized = self::normalize($locale);
        $byNormalized = [];
        foreach ($available as $option) {
            $byNormalized[self::normalize($option)] ??= $option;
        }

        return $byNormalized[$normalized]
            ?? $byNormalized[strtok($normalized, '-')]
            ?? null;
    }

    private static function normalize(string $locale): string
    {
        return strtolower(str_replace('_', '-', trim($locale)));
    }
}
