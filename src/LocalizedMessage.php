<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use Closure;
use InvalidArgumentException;

/**
 * One message per language. Expo sends title and body exactly as given, so {@see PushNotifier} picks the language
 * for each device ({@see LocaleResolver}) and sends that device the matching message.
 */
final class LocalizedMessage
{
    /** @var array<string, ExpoMessage> */
    private array $messages = [];

    /** @var list<string> */
    private array $locales;

    private ?string $fallback = null;

    /**
     * @param  list<string>  $locales
     * @param  (Closure(string): ExpoMessage)|null  $factory
     */
    private function __construct(array $locales, private readonly ?Closure $factory = null)
    {
        $this->locales = array_values(array_unique($locales));
    }

    /**
     * Messages written out per language, e.g. ['en' => ExpoMessage::create('Hi'), 'ar' => ExpoMessage::create('مرحبا')].
     *
     * @param  array<string, ExpoMessage>  $messages
     */
    public static function make(array $messages): self
    {
        if ($messages === []) {
            throw new InvalidArgumentException('A localized message needs at least one language.');
        }

        $message = new self(array_map(strval(...), array_keys($messages)));
        $message->messages = $messages;

        return $message;
    }

    /**
     * Builds each language's message on demand, e.g. from translated templates. The languages default to
     * `expo-notification.locales.supported`; only the ones a recipient needs are built.
     *
     * @param  Closure(string $locale): ExpoMessage  $factory
     * @param  list<string>|null  $locales
     */
    public static function using(Closure $factory, ?array $locales = null): self
    {
        return new self($locales ?? (array) config('expo-notification.locales.supported', []), $factory);
    }

    /** The language for devices whose language is unknown or not one of the message's languages. */
    public function fallback(string $locale): self
    {
        $this->fallback = $locale;

        return $this;
    }

    public function getFallback(): string
    {
        $fallback = $this->fallback
            ?? config('expo-notification.locales.fallback')
            ?? config('app.fallback_locale')
            ?? 'en';

        // With written-out messages, a fallback that has none uses the first language instead.
        return $this->factory === null && ! isset($this->messages[$fallback]) ? $this->locales[0] : $fallback;
    }

    /** @return list<string> the languages a device can be matched to, the fallback included */
    public function locales(): array
    {
        return array_values(array_unique([...$this->locales, $this->getFallback()]));
    }

    public function for(string $locale): ExpoMessage
    {
        if (! in_array($locale, $this->locales(), true)) {
            $locale = $this->getFallback();
        }

        return $this->messages[$locale] ??= ($this->factory)($locale);
    }
}
