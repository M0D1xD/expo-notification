<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Unit;

use InvalidArgumentException;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\LocaleResolver;
use M0d1xd\ExpoNotification\LocalizedMessage;
use M0d1xd\ExpoNotification\Tests\TestCase;

final class LocalizedMessageTest extends TestCase
{
    public function test_an_unknown_language_gets_the_fallback(): void
    {
        $message = LocalizedMessage::make(['en' => ExpoMessage::create('Hi'), 'ar' => ExpoMessage::create('مرحبا')])->fallback('ar');

        $this->assertSame('مرحبا', $message->for('fr')->toArray()['title']);
        $this->assertSame('Hi', $message->for('en')->toArray()['title']);
    }

    public function test_the_fallback_defaults_to_the_config_then_the_app_and_must_have_a_message(): void
    {
        config(['app.fallback_locale' => 'ar']);
        $this->assertSame('ar', LocalizedMessage::make(['en' => ExpoMessage::create('Hi'), 'ar' => ExpoMessage::create('مرحبا')])->getFallback());

        config(['expo-notification.locales.fallback' => 'en']);
        $this->assertSame('en', LocalizedMessage::make(['en' => ExpoMessage::create('Hi'), 'ar' => ExpoMessage::create('مرحبا')])->getFallback());

        // No message for the configured fallback: the first language is used.
        $this->assertSame('ar', LocalizedMessage::make(['ar' => ExpoMessage::create('مرحبا')])->getFallback());
    }

    public function test_a_factory_uses_the_supported_languages_and_builds_each_once(): void
    {
        config(['expo-notification.locales.supported' => ['en', 'ar']]);
        $calls = 0;
        $message = LocalizedMessage::using(function (string $locale) use (&$calls) {
            $calls++;

            return ExpoMessage::create($locale);
        })->fallback('en');

        $message->for('ar');
        $message->for('ar');

        $this->assertSame(['en', 'ar'], $message->locales());
        $this->assertSame(1, $calls);
    }

    public function test_it_needs_a_language(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocalizedMessage::make([]);
    }

    public function test_locales_match_by_region_then_language(): void
    {
        $resolver = new LocaleResolver;

        $this->assertSame('ar', $resolver->match('ar-SA', ['en', 'ar']));
        $this->assertSame('ar', $resolver->match('AR_eg', ['en', 'ar']));
        $this->assertSame('pt-BR', $resolver->match('pt_BR', ['pt', 'pt-BR']));
        $this->assertSame('pt', $resolver->match('pt-PT', ['pt', 'pt-BR']));
        $this->assertNull($resolver->match('fr', ['en', 'ar']));
    }
}
