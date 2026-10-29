<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use InvalidArgumentException;
use JsonSerializable;
use M0d1xd\ExpoNotification\Validation\ExpoPushTokenRule;
use Stringable;

/** An Expo push token such as `ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]`. */
final readonly class ExpoPushToken implements JsonSerializable, Stringable
{
    private const string PATTERN = '/^Expo(nent)?PushToken\[[^\[\]]+\]$/';

    private function __construct(private string $value) {}

    public static function make(string $value): self
    {
        if (! self::isValid($value)) {
            throw new InvalidArgumentException("[{$value}] is not a valid Expo push token.");
        }

        return new self($value);
    }

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }

    public static function rule(): ExpoPushTokenRule
    {
        return new ExpoPushTokenRule;
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
