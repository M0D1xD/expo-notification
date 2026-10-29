<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Console;

use Illuminate\Console\Command;
use M0d1xd\ExpoNotification\Exceptions\CouldNotSendNotification;
use M0d1xd\ExpoNotification\ExpoClient;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\ExpoPushToken;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushNotifier;
use M0d1xd\ExpoNotification\PushTicket;

/** Sends a test notification to one or more tokens and prints the push tickets; `--track` records it like PushNotifier. */
final class SendTestNotificationCommand extends Command
{
    protected $signature = 'expo-notification:send
        {tokens* : One or more Expo push tokens}
        {--title=Test notification : Notification title}
        {--body=Sent from expo-notification:send : Notification body}
        {--track : Send through PushNotifier to registered devices and record the deliveries}';

    protected $description = 'Send a test push notification through the Expo Push Service';

    public function handle(ExpoClient $client): int
    {
        $tokens = (array) $this->argument('tokens');

        foreach ($tokens as $token) {
            if (! ExpoPushToken::isValid($token)) {
                $this->components->error("[{$token}] is not a valid Expo push token.");

                return self::INVALID;
            }
        }

        $message = ExpoMessage::create((string) $this->option('title'), (string) $this->option('body'))->high();

        if ($this->option('track')) {
            return $this->track($message, $tokens);
        }

        try {
            $tickets = $client->send(
                $message,
                $tokens,
            );
        } catch (CouldNotSendNotification $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Token', 'Status', 'Ticket', 'Error'],
            $tickets->map(fn (PushTicket $t) => [$t->token, $t->status, $t->id ?? '—', $t->errorType?->value ?? ''])->all(),
        );

        return $tickets->every(fn (PushTicket $t) => $t->isOk()) ? self::SUCCESS : self::FAILURE;
    }

    /** @param  array<int, string>  $tokens */
    private function track(ExpoMessage $message, array $tokens): int
    {
        $registered = PushToken::query()->whereIn('push_token', $tokens)->get();
        $missing = array_diff($tokens, $registered->pluck('push_token')->all());

        if ($missing !== []) {
            $this->components->error('Not registered: '.implode(', ', $missing).'.');

            return self::INVALID;
        }

        $notification = $this->laravel->make(PushNotifier::class)->send($message, $registered);
        $deliveries = $notification->deliveries()->get();

        $this->components->info("Notification {$notification->id}");
        $this->table(
            ['Device', 'Token', 'Ticket status', 'Ticket', 'Error'],
            $deliveries->map(fn (PushNotificationDelivery $d) => [
                $registered->firstWhere('id', $d->push_token_id)?->device_id ?? '—',
                $d->push_token,
                $d->ticket_status,
                $d->ticket_id ?? '—',
                $d->error_type ?? $d->error_message ?? '',
            ])->all(),
        );

        return $deliveries->every(fn (PushNotificationDelivery $d) => $d->ticket_status === 'ok') ? self::SUCCESS : self::FAILURE;
    }
}
