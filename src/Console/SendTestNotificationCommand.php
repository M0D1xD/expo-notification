<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Console;

use Illuminate\Console\Command;
use M0d1xd\ExpoNotification\Exceptions\CouldNotSendNotification;
use M0d1xd\ExpoNotification\ExpoClient;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\ExpoPushToken;
use M0d1xd\ExpoNotification\PushTicket;

/** Sends a test notification to one or more tokens and prints the push tickets. */
final class SendTestNotificationCommand extends Command
{
    protected $signature = 'expo-notification:send
        {tokens* : One or more Expo push tokens}
        {--title=Test notification : Notification title}
        {--body=Sent from expo-notification:send : Notification body}';

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

        try {
            $tickets = $client->send(
                ExpoMessage::create((string) $this->option('title'), (string) $this->option('body'))->high(),
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
}
