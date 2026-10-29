# m0d1xd/expo-notification

Send Expo push notifications from Laravel, and (in upcoming releases) track every notification per device:
delivered, opened and dismissed.

> **Status: early development.** This release ships the sending API and the database schema.
> Token registration, receipt tracking and device events come in the next milestones.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require m0d1xd/expo-notification
php artisan vendor:publish --tag=expo-notification-config
php artisan vendor:publish --tag=expo-notification-migrations
php artisan migrate
```

If "Enhanced Security for Push Notifications" is enabled for your project on expo.dev, set the access token:

```dotenv
EXPO_ACCESS_TOKEN=your-expo-access-token
```

## Sending

```php
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\Facades\ExpoNotification;

$tickets = ExpoNotification::send(
    ExpoMessage::create('New message', 'Ali sent you a photo')
        ->data(['url' => '/chats/12'])
        ->channelId('default')
        ->high(),
    ['ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]', 'ExponentPushToken[yyyyyyyyyyyyyyyyyyyyyy]'],
);

foreach ($tickets as $ticket) {
    $ticket->token;      // the recipient
    $ticket->isOk();     // accepted by the Expo Push Service
    $ticket->id;         // use it to fetch the receipt
    $ticket->errorType;  // ExpoErrorType when rejected, e.g. DeviceNotRegistered
}
```

- One message is sent per token; duplicate tokens are dropped.
- Requests are chunked at 100 messages (Expo's limit), gzip-compressed above 1 KiB, and retried on 429/5xx.
- A rejected message never throws: it comes back as an error ticket and dispatches
  `M0d1xd\ExpoNotification\Events\PushFailed`. Only a failed HTTP request throws `CouldNotSendNotification`.

### Receipts

```php
$receipts = ExpoNotification::receipts($tickets->pluck('id')->filter()); // keyed by ticket id

$receipts['…']->isOk();
$receipts['…']->errorType?->isDeviceNotRegistered();
```

Receipts are fetched in chunks of 1000 ids. Ids that Expo is still processing are omitted.

> A receipt `ok` only means Expo handed the message to FCM/APNs. It does not mean the device received it.

### Message options

| Method | Notes |
|---|---|
| `title()`, `body()` / `text()`, `subtitle()` (iOS) | must not be empty |
| `data(array\|Arrayable\|JsonSerializable)` | sent as a JSON object |
| `badge(int)` (iOS) | ≥ 0 |
| `playSound()` (iOS) | default sound |
| `priority('default'\|'normal'\|'high')`, `default()`, `normal()`, `high()` | |
| `ttl(int)` / `expiresIn(int)`, `expiresAt(DateTimeInterface\|int)` | ttl > 0, expiration in the future |
| `channelId()` (Android), `categoryId()` | must not be empty |
| `mutableContent()` (iOS), `contentAvailable()` (iOS) | |
| `richContent(string $imageUrl)` | |
| `when()` / `unless()` | conditional building |

### Push tokens

```php
use M0d1xd\ExpoNotification\ExpoPushToken;
use M0d1xd\ExpoNotification\Casts\AsExpoPushToken;

$request->validate(['token' => ['required', ExpoPushToken::rule()]]);

protected $casts = ['expo_token' => AsExpoPushToken::class];
```

### Test command

```bash
php artisan expo-notification:send "ExponentPushToken[xxx]" --title="Hello" --body="World"
```

## Database schema

| Table | Purpose |
|---|---|
| `push_tokens` | One row per device: `device_id`, `push_token`, `platform`, `locale`, nullable owner |
| `push_notifications` | Each notification sent (ULID id, also sent to devices as `data.notificationId`) |
| `push_notification_deliveries` | Per notification per device: Expo ticket/receipt, `delivered_at`, `opened_at`, `dismissed_at` |
| `push_notification_events` | Raw device events (received / opened / dismissed), stored once each |

The owner is configurable in `config/expo-notification.php`:

```php
'owner' => [
    'mode' => 'morph',      // owner_type + owner_id, any model; or 'foreign' for a plain FK
    'key_type' => 'int',    // 'int', 'uuid' or 'ulid'
    'model' => App\Models\User::class, // 'foreign' mode only
    'column' => 'user_id',             // 'foreign' mode only
],
```

Table names are configurable under `tables`.

## Coming from laravel-notification-channels/expo

The sending API follows [laravel-notification-channels/expo](https://github.com/laravel-notification-channels/expo)
(MIT). Differences:

- A standalone API (`ExpoNotification::send()`), not a Laravel notification channel.
- `data` is sent as a JSON object, as the Expo API documents.
- Requests are chunked at 100 messages, and receipts at 1000 ids.
- Unknown Expo error codes map to `ExpoErrorType::Unknown` instead of throwing.

## Testing

```bash
composer test
composer lint
```

## License

MIT
