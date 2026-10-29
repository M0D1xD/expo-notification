<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use Illuminate\Database\Eloquent\Builder;
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

    public function __construct(private readonly ExpoClient $client, private readonly DeliveryFailureHandler $failures) {}

    /**
     * Only active tokens are sent to, once per device. The message itself is not changed; the copy that is sent
     * carries `data.notificationId`.
     *
     * @param  PushToken|Model|Builder<PushToken>|Relation<PushToken, Model, mixed>|iterable<mixed>  $recipients
     *                                                                                                            tokens, owners using HasPushTokens, token queries, or a mix
     */
    public function send(ExpoMessage $message, mixed $recipients): PushNotification
    {
        $tokens = $this->resolve($recipients);
        $notification = new PushNotification;
        $notification->id = $notification->newUniqueId();

        $sent = (clone $message)->data([...(array) ($message->toArray()['data'] ?? []), 'notificationId' => $notification->id]);
        $payload = $sent->toArray();

        $deliveries = $notification->getConnection()->transaction(function () use ($notification, $payload, $tokens) {
            $notification->fill([
                'title' => $payload['title'] ?? null,
                'body' => $payload['body'] ?? null,
                'data' => $payload['data'],
                'payload' => $payload,
                'sent_at' => now(),
            ])->save();

            return $tokens->map(fn (PushToken $token) => $notification->deliveries()->create([
                'push_token_id' => $token->id,
                'push_token' => $token->push_token,
                'sent_at' => now(),
            ]));
        });

        $deliveries->chunk(self::CHUNK_SIZE)->each(fn (Collection $chunk) => $this->sendChunk($sent, $chunk->values()));

        return $notification;
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
