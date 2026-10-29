# m0d1xd/expo-notification

Send Expo push notifications from Laravel and track every notification per device: sent, delivered and opened.
The React Native side is [expo-notification-client](https://github.com/M0D1xD/expo-notification-client).

> **Status: early development.** Android is verified on real devices; iOS is not tested yet.

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

## Devices

Each device registers its Expo push token through the package routes. `device_id` is the platform install ID
(Android ID / iOS identifierForVendor). Every request carries the `device_id` + `push_token` pair.

| Route | Body | Response |
|---|---|---|
| `PUT /expo-notification/push-tokens` | `device_id`, `push_token`, `platform` (`ios`/`android`), `locale?`, `previous_push_token?` | `201` created / `200` refreshed |
| `POST /expo-notification/push-tokens/detach` | `device_id`, `push_token` | `204`, call on logout |
| `DELETE /expo-notification/push-tokens` | `device_id`, `push_token` | `204`, the device stops receiving pushes |

- One row per device, and a push token belongs to one device. A token that shows up on a new device_id moves there.
- The owner is the user signed in on `routes.guard` (set it to `sanctum` for token-authenticated apps). Guests can register.
- A guest refresh with the same token keeps the owner, so registering before the session loads doesn't log anyone out.
  Only `detach` clears it.
- When the token changes, send the old one as `previous_push_token` to keep the owner. Without it the owner is cleared,
  so knowing a device_id alone never takes over someone else's notifications.
- `detach` and `DELETE` answer `404` unless both values match a stored device.

```php
// config/expo-notification.php
'routes' => [
    'enabled' => true,
    'prefix' => 'expo-notification',
    'middleware' => ['api', 'throttle:60,1'],
    'guard' => null, // the default guard, or e.g. 'sanctum'
],
```

Add `HasPushTokens` to the owner model to send to all of a user's devices:

```php
use M0d1xd\ExpoNotification\Concerns\HasPushTokens;

class User extends Authenticatable
{
    use HasPushTokens;
}

ExpoNotification::send(ExpoMessage::create('Hi', 'Welcome back'), $user->expoPushTokens());
```

The same rules are available without HTTP through `M0d1xd\ExpoNotification\PushTokenRegistry`
(`register()`, `find()`, `detach()`, `deactivate()`).

## Tracked sending

`PushNotifier` sends to registered devices and records the result per device:

```php
use M0d1xd\ExpoNotification\Facades\PushNotifier;

$notification = PushNotifier::send(
    ExpoMessage::create('New message', 'Ali sent you a photo')->data(['url' => '/chats/12']),
    $user, // or a PushToken, a PushToken query, $user->pushTokens(), or an array mixing them
);

$notification->deliveries; // one row per device: ticket_id, ticket_status, receipt_status, error_type, ...
```

- Only active tokens are targeted, once per device.
- The sent copy carries `data.notificationId` (the notification's ULID); the SDK owns that key. Your message object is not changed.
- `ticket_status` is `ok`, `error` (Expo rejected the message) or `failed` (the request to Expo failed). Messages go out
  in requests of 100; a failed request only fails its own deliveries.
- Every ticket or receipt error dispatches `M0d1xd\ExpoNotification\Events\DeliveryFailed`.
- `DeviceNotRegistered` deactivates the token. The device is reactivated when it registers again.
- Sending is synchronous. For large audiences, call `PushNotifier::send()` from a queued job.

### Receipts

`expo-notification:check-receipts` fetches receipts for deliveries sent at least `receipts.delay` minutes ago and
stores `receipt_status` (`ok` / `error`). Receipts Expo never returns are marked `expired` after
`receipts.expire_after` hours. The package schedules the command every 15 minutes, which needs the
[scheduler](https://laravel.com/docs/scheduling#running-the-scheduler) running.

```php
'receipts' => [
    'delay' => 15,                 // minutes
    'expire_after' => 24,          // hours
    'schedule' => '*/15 * * * *',  // null to schedule it yourself
],
```

> A receipt `ok` means Expo handed the message to FCM/APNs. It does not mark the delivery as delivered:
> `delivered_at` is set when the device reports it.

### Tracking payloads

With `tracking.enabled` (the default), every tracked send can be recorded in the background:

| Platform | What is sent | Why |
|---|---|---|
| Android | A data-only push: title, body, `channelId`, `categoryId` and the image move into `data.render`, priority `high` | Only data-only pushes wake the app's background task when it is in the background or killed. The client shows the notification itself |
| iOS | The message plus `_contentAvailable: true` | Wakes the app for background processing (throttled by iOS). Not verified on devices yet |

> **Android users see nothing unless the app uses expo-notification-client** (or shows `data.render` itself).
> Without the client, set `EXPO_NOTIFICATION_TRACKING=false` to send normal pushes.

The SDK owns the `data.notificationId` and `data.render` keys.

## Device events

The client reports what happened to each notification to `POST /expo-notification/push-events`:

```json
{
  "device_id": "…", "push_token": "ExponentPushToken[…]",
  "events": [{ "notification_id": "01…", "type": "received", "source": "task", "app_state": "background", "occurred_at": "2026-10-29T10:00:00Z" }]
}
```

- Up to 100 events per request. The `device_id` + `push_token` pair must match a stored device (`404` otherwise).
- An event is stored only for a notification that was sent to that device; others are counted as `ignored`. Each
  notification, device and type is stored once (`duplicates`). The answer is `200 {"stored", "duplicates", "ignored"}`.
- `occurred_at` is kept between the send time and now.
- Each stored event updates the delivery: `received` sets `delivered_at` (the earliest wins), `opened` sets `opened_at`,
  `dismissed` sets `dismissed_at`. Opened and dismissed also fill `delivered_at`, since the notification arrived.
- Every stored event dispatches `M0d1xd\ExpoNotification\Events\NotificationEventRecorded`.

```php
$notification->deliveries()->whereNotNull('delivered_at')->count(); // delivered
$notification->deliveries()->whereNotNull('opened_at')->count();    // opened
$notification->events; // the raw device events
```

### What can be tracked (Android, verified on devices)

| App state | received | opened |
|---|---|---|
| Foreground | yes | yes |
| Background | yes | yes |
| Killed by the system | yes | yes (on launch) |
| Force-stopped by the user | no: Android does not start the app until the user opens it; the receipt is still `ok` | — |

Dismissals cannot be detected on Android. iOS has not been tested yet.

## Analytics

`DeliveryStats` counts any set of deliveries: one notification, one device, a date range.

```php
use M0d1xd\ExpoNotification\DeliveryStats;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;

$stats = $notification->stats();
$stats = DeliveryStats::for(PushNotificationDelivery::query()->where('sent_at', '>=', now()->subDays(7)));
$stats = DeliveryStats::for($device->deliveries()->getQuery());

$stats->targeted;       // deliveries created
$stats->accepted;       // ticket ok
$stats->failed;         // ticket error/failed or receipt error
$stats->delivered;      // reported by the device
$stats->opened;
$stats->dismissed;
$stats->deliveryRate(); // delivered / targeted, 0–1, null when nothing was targeted
$stats->openRate();     // opened / delivered, 0–1, null when nothing was delivered

// A list of notifications with their counts in one query:
PushNotification::query()->withDeliveryStats()->latest('sent_at')->paginate();
// adds deliveries_count, accepted_count, failed_count, delivered_count, opened_count, dismissed_count;
// $notification->stats() then reads them without querying again.
```

The same definitions are available as delivery scopes: `failed()`, `delivered()`, `opened()`, `dismissed()`.
Delivered never counts a receipt `ok`: only the device can say it got the notification.

## Test command

```bash
php artisan expo-notification:send "ExponentPushToken[xxx]" --title="Hello" --body="World"
# Through PushNotifier, to registered devices, recording deliveries:
php artisan expo-notification:send "ExponentPushToken[xxx]" --track
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
