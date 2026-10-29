<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * A push message for the Expo Push Service. Mirrors the builder of laravel-notification-channels/expo,
 * except that `data` is sent as a JSON object, as the Expo API documents.
 *
 * @see https://docs.expo.dev/push-notifications/sending-notifications/#message-request-format
 */
final class ExpoMessage implements Arrayable, JsonSerializable
{
    use Conditionable;

    private const array PRIORITIES = ['default', 'normal', 'high'];

    private ?string $title = null;

    private ?string $body = null;

    /** @var array<array-key, mixed>|null */
    private ?array $data = null;

    private ?int $ttl = null;

    private ?int $expiration = null;

    private string $priority = 'default';

    /** iOS only. */
    private ?string $subtitle = null;

    /** iOS only. */
    private ?string $sound = null;

    /** iOS only. */
    private int $badge = 0;

    /** Android only. */
    private ?string $channelId = null;

    private ?string $categoryId = null;

    /** iOS only. */
    private ?bool $mutableContent = null;

    /** iOS only: wakes the app for background processing. */
    private ?bool $_contentAvailable = null;

    /** @var array{image: string}|null */
    private ?array $richContent = null;

    private function __construct(string $title, string $body)
    {
        $this->title = $title;
        $this->body = $body;
    }

    public static function create(string $title = '', string $body = ''): self
    {
        return new self($title, $body);
    }

    public function title(string $value): self
    {
        $this->title = $this->filledString($value, 'title');

        return $this;
    }

    public function body(string $value): self
    {
        $this->body = $this->filledString($value, 'body');

        return $this;
    }

    public function text(string $value): self
    {
        return $this->body($value);
    }

    public function subtitle(string $value): self
    {
        $this->subtitle = $this->filledString($value, 'subtitle');

        return $this;
    }

    /**
     * @param  Arrayable<array-key, mixed>|Jsonable|JsonSerializable|array<array-key, mixed>  $value
     */
    public function data(Arrayable|Jsonable|JsonSerializable|array $value): self
    {
        $this->data = match (true) {
            $value instanceof Arrayable => $value->toArray(),
            $value instanceof JsonSerializable => (array) $value->jsonSerialize(),
            $value instanceof Jsonable => json_decode($value->toJson(), true, flags: JSON_THROW_ON_ERROR),
            default => $value,
        };

        return $this;
    }

    public function badge(int $value): self
    {
        if ($value < 0) {
            throw new InvalidArgumentException('The badge must be greater than or equal to 0.');
        }

        $this->badge = $value;

        return $this;
    }

    public function playSound(): self
    {
        $this->sound = 'default';

        return $this;
    }

    public function priority(string $value): self
    {
        $value = strtolower($value);

        if (! in_array($value, self::PRIORITIES, true)) {
            throw new InvalidArgumentException('The priority must be default, normal or high.');
        }

        $this->priority = $value;

        return $this;
    }

    public function default(): self
    {
        return $this->priority('default');
    }

    public function normal(): self
    {
        return $this->priority('normal');
    }

    public function high(): self
    {
        return $this->priority('high');
    }

    /** Seconds the message may be kept for redelivery. */
    public function ttl(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('The TTL must be greater than 0.');
        }

        $this->ttl = $value;

        return $this;
    }

    public function expiresIn(int $value): self
    {
        return $this->ttl($value);
    }

    public function expiresAt(DateTimeInterface|int $value): self
    {
        $timestamp = $value instanceof DateTimeInterface ? $value->getTimestamp() : $value;

        if ($timestamp <= time()) {
            throw new InvalidArgumentException('The expiration time must be in the future.');
        }

        $this->expiration = $timestamp;

        return $this;
    }

    public function channelId(string $value): self
    {
        $this->channelId = $this->filledString($value, 'channelId');

        return $this;
    }

    public function categoryId(string $value): self
    {
        $this->categoryId = $this->filledString($value, 'categoryId');

        return $this;
    }

    public function mutableContent(bool $value = true): self
    {
        $this->mutableContent = $value;

        return $this;
    }

    public function contentAvailable(bool $value = true): self
    {
        $this->_contentAvailable = $value;

        return $this;
    }

    public function richContent(string $imageUrl): self
    {
        $this->richContent = ['image' => $this->filledString($imageUrl, 'image')];

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'ttl' => $this->ttl,
            'expiration' => $this->expiration,
            'priority' => $this->priority,
            'subtitle' => $this->subtitle,
            'sound' => $this->sound,
            'badge' => $this->badge,
            'channelId' => $this->channelId,
            'categoryId' => $this->categoryId,
            'mutableContent' => $this->mutableContent,
            '_contentAvailable' => $this->_contentAvailable,
            'richContent' => $this->richContent,
        ], filled(...));
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function filledString(string $value, string $field): string
    {
        if ($value === '') {
            throw new InvalidArgumentException("The {$field} must not be empty.");
        }

        return $value;
    }
}
