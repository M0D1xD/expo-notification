<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use M0d1xd\ExpoNotification\Events\PushFailed;
use M0d1xd\ExpoNotification\Exceptions\CouldNotGetReceipts;
use M0d1xd\ExpoNotification\Exceptions\CouldNotSendNotification;
use Throwable;

/**
 * Talks to the Expo Push Service.
 *
 * @see https://docs.expo.dev/push-notifications/sending-notifications/
 */
final class ExpoClient
{
    /** Expo accepts at most 100 messages per send request. */
    private const int SEND_CHUNK_SIZE = 100;

    /** Expo accepts at most 1000 ticket ids per receipts request. */
    private const int RECEIPTS_CHUNK_SIZE = 1000;

    /** @param  array<string, mixed>  $config  the `expo-notification` config */
    public function __construct(
        private readonly Factory $http,
        private readonly Dispatcher $events,
        private readonly array $config,
    ) {}

    /**
     * Send the message to every token, one message per token. Rejected messages come back as
     * error tickets and dispatch {@see PushFailed}; only a failed HTTP request throws.
     *
     * @param  iterable<ExpoPushToken|string>  $tokens
     * @return Collection<int, PushTicket> in the same order as the (de-duplicated) tokens
     *
     * @throws CouldNotSendNotification
     */
    public function send(ExpoMessage $message, iterable $tokens): Collection
    {
        $payload = $message->toArray();

        return collect($tokens)
            ->map(fn (ExpoPushToken|string $token) => (string) $token)
            ->unique()
            ->values()
            ->chunk(self::SEND_CHUNK_SIZE)
            ->flatMap(function (Collection $chunk) use ($payload) {
                $tokens = $chunk->values();
                $response = $this->post('send', $tokens->map(fn ($to) => ['to' => $to] + $payload)->all());

                if (! $response->successful()) {
                    throw CouldNotSendNotification::fromResponse($response);
                }

                $tickets = $response->json('data', []);

                return $tokens->map(fn (string $token, int $i) => PushTicket::fromApi(
                    $token,
                    $tickets[$i] ?? ['status' => 'error', 'message' => 'No ticket returned for this message.'],
                ));
            })
            ->each(function (PushTicket $ticket): void {
                if ($ticket->isError()) {
                    $this->events->dispatch(new PushFailed($ticket));
                }
            })
            ->values();
    }

    /**
     * Fetch receipts for ticket ids. Ids Expo does not know yet (still processing) are omitted.
     *
     * @param  iterable<string>  $ticketIds
     * @return Collection<string, PushReceipt> keyed by ticket id
     *
     * @throws CouldNotGetReceipts
     */
    public function receipts(iterable $ticketIds): Collection
    {
        return collect($ticketIds)
            ->filter()
            ->unique()
            ->values()
            ->chunk(self::RECEIPTS_CHUNK_SIZE)
            ->flatMap(function (Collection $ids) {
                $response = $this->post('getReceipts', ['ids' => $ids->values()->all()]);

                if (! $response->successful()) {
                    throw CouldNotGetReceipts::fromResponse($response);
                }

                return collect($response->json('data', []))
                    ->map(fn (array $receipt, string $id) => PushReceipt::fromApi($id, $receipt));
            });
    }

    /** @param  array<array-key, mixed>  $body */
    private function post(string $endpoint, array $body): Response
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR);
        $request = $this->request();

        if (strlen($json) > (int) ($this->config['gzip_threshold'] ?? 1024) && function_exists('gzencode')) {
            $request->withHeaders(['Content-Encoding' => 'gzip']);
            $json = gzencode($json, 6);
        }

        return $request->withBody($json, 'application/json')->post($endpoint);
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->timeout((int) ($this->config['timeout'] ?? 30))
            ->acceptJson()
            ->withHeaders(['Accept-Encoding' => 'gzip, deflate'])
            ->when($this->config['access_token'] ?? null, fn (PendingRequest $r, string $token) => $r->withToken($token))
            ->retry(
                times: 1 + (int) ($this->config['retries'] ?? 2),
                sleepMilliseconds: fn (int $attempt) => 500 * 2 ** ($attempt - 1),
                when: fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())),
                throw: false,
            );
    }
}
