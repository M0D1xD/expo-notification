<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use M0d1xd\ExpoNotification\Exceptions\CouldNotSendNotification;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushToken;

/** Sends a message to registered devices and records the notification, one delivery per device and each Expo ticket. */
final class PushNotifier
{
    /** One request to the Expo Push Service per chunk, so a failed request only affects its own deliveries. */
    private const int CHUNK_SIZE = 100;

    public function __construct(
        private readonly ExpoClient $client,
        private readonly DeliveryFailureHandler $failures,
        private readonly LocaleResolver $localeResolver,
    ) {}

    /**
     * Only active tokens are sent to, once per device. The message itself is not changed; the copy that is sent
     * carries `data.notificationId`. A {@see LocalizedMessage} sends each device the message in its language
     * ({@see LocaleResolver}); the notification keeps the fallback language's text and every language's title and
     * body in `translations`, and each delivery its `locale`.
     *
     * @param  PushToken|Model|Builder<PushToken>|Relation<PushToken, Model, mixed>|iterable<mixed>  $recipients
     *                                                                                                            tokens, owners using HasPushTokens, token queries, or a mix
     */
    public function send(ExpoMessage|LocalizedMessage $message, mixed $recipients): PushNotification
    {
        $tokens = $this->resolve($recipients);
        $notification = new PushNotification;
        $notification->id = $notification->newUniqueId();

        // Keyed by locale; a plain message is sent under the key "".
        $locales = $this->locales($message, $tokens);
        $fallback = $message instanceof LocalizedMessage ? $message->getFallback() : '';
        $sent = collect([$fallback, ...$locales->unique()->values()])
            ->unique()
            ->mapWithKeys(fn (string $locale) => [$locale => self::withNotificationId(
                $message instanceof LocalizedMessage ? $message->for($locale) : $message,
                $notification->id,
            )]);
        $payload = $sent[$fallback]->toArray();

        $deliveries = $notification->getConnection()->transaction(function () use ($notification, $payload, $tokens, $sent, $locales) {
            $notification->fill([
                'title' => $payload['title'] ?? null,
                'body' => $payload['body'] ?? null,
                'data' => $payload['data'],
                'payload' => $payload,
                'translations' => $locales->isEmpty() ? null : $sent
                    ->only($locales->unique()->all())
                    ->map(fn (ExpoMessage $localized) => array_intersect_key($localized->toArray(), ['title' => 0, 'subtitle' => 0, 'body' => 0]))
                    ->all(),
                'sent_at' => now(),
            ])->save();

            return $tokens->map(fn (PushToken $token) => $notification->deliveries()->create([
                'push_token_id' => $token->id,
                'push_token' => $token->push_token,
                'locale' => $locales[$token->id] ?? null,
                'sent_at' => now(),
            ]));
        });

        $platforms = $tokens->pluck('platform', 'id');

        $deliveries
            ->groupBy(fn (PushNotificationDelivery $delivery) => $delivery->locale ?? '')
            ->each(fn (Collection $byLocale, string $locale) => $byLocale
                ->groupBy(fn (PushNotificationDelivery $delivery) => $platforms[$delivery->push_token_id])
                ->each(fn (Collection $group, string $platform) => $group
                    ->chunk(self::CHUNK_SIZE)
                    ->each(fn (Collection $chunk) => $this->sendChunk($this->forPlatform($sent[$locale], $platform), $chunk->values()))));

        return $notification;
    }

    private static function withNotificationId(ExpoMessage $message, string $id): ExpoMessage
    {
        return (clone $message)->data([...(array) ($message->toArray()['data'] ?? []), 'notificationId' => $id]);
    }

    /**
     * Each token's language, keyed by token id; empty for a plain message.
     *
     * @param  Collection<int, PushToken>  $tokens
     * @return Collection<int, string>
     */
    private function locales(ExpoMessage|LocalizedMessage $message, Collection $tokens): Collection
    {
        if (! $message instanceof LocalizedMessage) {
            return collect();
        }

        (new EloquentCollection($tokens->all()))->load('owner');
        $available = $message->locales();
        $fallback = $message->getFallback();

        return $tokens->mapWithKeys(fn (PushToken $token) => [$token->id => $this->localeResolver->resolve($token, $available, $fallback)]);
    }

    /**
     * With `tracking.enabled`, every send can be tracked in the background: Android gets a data-only push that the
     * client SDK shows itself, iOS gets `_contentAvailable` so the app wakes up.
     */
    private function forPlatform(ExpoMessage $message, string $platform): ExpoMessage
    {
        if (! config('expo-notification.tracking.enabled', true)) {
            return $message;
        }

        return match ($platform) {
            'android' => $message->asDataOnly(),
            'ios' => (clone $message)->contentAvailable(),
            default => $message,
        };
    }

    /** @param  Collection<int, PushNotificationDelivery>  $deliveries */
    private function sendChunk(ExpoMessage $message, Collection $deliveries): void
    {
        try {
            $tickets = $this->client->send($message, $deliveries->pluck('push_token'));
        } catch (CouldNotSendNotification $e) {
            foreach ($deliveries as $delivery) {
                $delivery->forceFill(['ticket_status' => 'failed', 'error_message' => $e->getMessage()])->save();
                $this->failures->handle($delivery);
            }

            return;
        }

        // Tokens are unique per notification, so tickets line up with deliveries.
        foreach ($tickets as $i => $ticket) {
            $delivery = $deliveries[$i];
            $delivery->forceFill([
                'ticket_id' => $ticket->id,
                'ticket_status' => $ticket->status,
                'error_type' => $ticket->errorType?->value,
                'error_message' => $ticket->message,
                'details' => $ticket->details ?: null,
            ])->save();

            if ($ticket->isError()) {
                $this->failures->handle($delivery);
            }
        }
    }

    /** @return Collection<int, PushToken> */
    private function resolve(mixed $recipients): Collection
    {
        $items = is_iterable($recipients) && ! $recipients instanceof Model ? $recipients : [$recipients];

        return collect($items)
            ->flatMap(fn (mixed $recipient) => match (true) {
                $recipient instanceof PushToken => [$recipient],
                $recipient instanceof Builder, $recipient instanceof Relation => $recipient->get()->all(),
                $recipient instanceof Model && method_exists($recipient, 'pushTokens') => $recipient->pushTokens()->get()->all(),
                default => throw new InvalidArgumentException(
                    'Recipients must be PushToken models, owners using HasPushTokens, or PushToken queries; got '.get_debug_type($recipient).'.',
                ),
            })
            ->filter(fn (PushToken $token) => $token->active)
            ->unique('id')
            ->values();
    }
}
