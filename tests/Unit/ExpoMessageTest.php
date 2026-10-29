<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use M0d1xd\ExpoNotification\ExpoMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExpoMessageTest extends TestCase
{
    public function test_it_serializes_only_filled_keys_with_defaults(): void
    {
        $this->assertSame(
            ['title' => 'Hello', 'body' => 'World', 'priority' => 'default', 'badge' => 0],
            ExpoMessage::create('Hello', 'World')->toArray(),
        );
    }

    public function test_it_serializes_every_option(): void
    {
        $message = ExpoMessage::create()
            ->title('Title')
            ->text('Body')
            ->subtitle('Sub')
            ->data(['url' => '/chats/12'])
            ->badge(3)
            ->playSound()
            ->high()
            ->ttl(60)
            ->channelId('default')
            ->categoryId('tracked')
            ->mutableContent()
            ->contentAvailable()
            ->richContent('https://example.com/a.png');

        $this->assertSame([
            'title' => 'Title',
            'body' => 'Body',
            'data' => ['url' => '/chats/12'],
            'ttl' => 60,
            'priority' => 'high',
            'subtitle' => 'Sub',
            'sound' => 'default',
            'badge' => 3,
            'channelId' => 'default',
            'categoryId' => 'tracked',
            'mutableContent' => true,
            '_contentAvailable' => true,
            'richContent' => ['image' => 'https://example.com/a.png'],
        ], $message->toArray());
    }

    public function test_data_is_sent_as_an_object_not_a_string(): void
    {
        $json = json_encode(ExpoMessage::create('a', 'b')->data(['id' => 7]));

        $this->assertStringContainsString('"data":{"id":7}', $json);
    }

    public function test_data_accepts_arrayable_and_json_serializable(): void
    {
        $object = new class implements \JsonSerializable
        {
            public function jsonSerialize(): array
            {
                return ['k' => 'v'];
            }
        };

        $this->assertSame(['k' => 'v'], ExpoMessage::create()->data($object)->toArray()['data']);
        $this->assertSame(['a' => 1], ExpoMessage::create()->data(collect(['a' => 1]))->toArray()['data']);
    }

    public function test_priority_helpers_and_case_insensitive_priority(): void
    {
        $this->assertSame('normal', ExpoMessage::create()->normal()->toArray()['priority']);
        $this->assertSame('default', ExpoMessage::create()->high()->default()->toArray()['priority']);
        $this->assertSame('high', ExpoMessage::create()->priority('HIGH')->toArray()['priority']);
    }

    public function test_expires_in_sets_ttl_and_expires_at_sets_expiration(): void
    {
        $this->assertSame(120, ExpoMessage::create()->expiresIn(120)->toArray()['ttl']);

        $at = new DateTimeImmutable('+1 hour');
        $this->assertSame($at->getTimestamp(), ExpoMessage::create()->expiresAt($at)->toArray()['expiration']);
        $this->assertSame(time() + 60, ExpoMessage::create()->expiresAt(time() + 60)->toArray()['expiration']);
    }

    public function test_conditionable(): void
    {
        $message = ExpoMessage::create()->when(true, fn ($m) => $m->badge(1))->unless(true, fn ($m) => $m->badge(9));

        $this->assertSame(1, $message->toArray()['badge']);
    }

    /** @return iterable<string, array{callable(ExpoMessage): mixed, string}> */
    public static function invalidInput(): iterable
    {
        yield 'negative badge' => [fn (ExpoMessage $m) => $m->badge(-1), 'badge'];
        yield 'zero ttl' => [fn (ExpoMessage $m) => $m->ttl(0), 'TTL'];
        yield 'past expiration' => [fn (ExpoMessage $m) => $m->expiresAt(time() - 1), 'expiration'];
        yield 'unknown priority' => [fn (ExpoMessage $m) => $m->priority('urgent'), 'priority'];
        yield 'empty title' => [fn (ExpoMessage $m) => $m->title(''), 'title'];
        yield 'empty body' => [fn (ExpoMessage $m) => $m->body(''), 'body'];
        yield 'empty subtitle' => [fn (ExpoMessage $m) => $m->subtitle(''), 'subtitle'];
        yield 'empty channel' => [fn (ExpoMessage $m) => $m->channelId(''), 'channelId'];
        yield 'empty category' => [fn (ExpoMessage $m) => $m->categoryId(''), 'categoryId'];
        yield 'empty image' => [fn (ExpoMessage $m) => $m->richContent(''), 'image'];
    }

    #[DataProvider('invalidInput')]
    public function test_it_rejects_invalid_input(callable $apply, string $field): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($field);

        $apply(ExpoMessage::create());
    }
}
