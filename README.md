# m0d1xd/expo-notification

[![Packagist](https://img.shields.io/packagist/v/m0d1xd/expo-notification)](https://packagist.org/packages/m0d1xd/expo-notification)
[![CI](https://github.com/M0D1xD/expo-notification/actions/workflows/ci.yml/badge.svg)](https://github.com/M0D1xD/expo-notification/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

Send Expo push notifications from Laravel and track every notification per device: sent, delivered and opened.
The React Native side is [expo-notification-client](https://github.com/M0D1xD/expo-notification-client).

> **Status: 0.x.** Android is verified on real devices; iOS is not tested yet. Laravel 12 and 13 are supported; CI
> runs on Laravel 13.

## Quickstart

**Laravel**

```bash
composer require m0d1xd/expo-notification
php artisan expo-notification:install
```

```php
use M0d1xd\ExpoNotification\Concerns\HasPushTokens;

class User extends Authenticatable
{
    use HasPushTokens;
}
```

The device routes are registered under `/expo-notification` (see [Devices](#devices)). Run the
[scheduler](https://laravel.com/docs/scheduling#running-the-scheduler) so receipts are checked.

**Expo app** ([client setup](https://github.com/M0D1xD/expo-notification-client#installation))

```bash
npx expo install expo-notification-client expo-notifications expo-task-manager expo-application expo-device \
  expo-constants @react-native-async-storage/async-storage
```

```ts
// src/push.ts, imported first by index.ts
import { configure, defineBackgroundTask } from 'expo-notification-client';

configure({ apiUrl: 'https://api.example.com' });
defineBackgroundTask();
```

```ts
// in the root component
useEffect(() => {
  register();
  return startTracking({ onOpen: (data) => data.url && router.push(String(data.url)) });
}, []);
```

**Send and read the results**

```php
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\Facades\PushNotifier;

$notification = PushNotifier::send(ExpoMessage::create('Hello', 'From Laravel')->data(['url' => '/details']), $user);

$notification->stats(); // targeted, accepted, failed, delivered, opened, ... and rates
$notification->deliveries; // per device: ticket, receipt, delivered_at, opened_at
```

## Examples

- [`example/`](example): a Laravel + Filament app with the package's [Filament dashboard](#filament-dashboard) and
  demo data.
- [expo-notification-client/example](https://github.com/M0D1xD/expo-notification-client/tree/main/example): the Expo
  app that registers with it and reports events.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require m0d1xd/expo-notification
php artisan expo-notification:install
```

The install command:

- publishes `config/expo-notification.php` (an existing one is kept; `--force` overwrites it);
- publishes the migrations once (running it again doesn't copy them under new dates);
- adds `EXPO_ACCESS_TOKEN` and `EXPO_NOTIFICATION_TRACKING` to `.env` and `.env.example` when they're missing, and never
  changes existing values (`--without-env` skips this);
- asks to run the migrations.

For custom table names or the `foreign` owner mode, answer **no** to the migrate question, edit
`config/expo-notification.php` (see [Table names](#table-names)), then run `php artisan migrate`.

Or step by step:

```bash
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

### Multi-language notifications

Expo does not translate: it delivers a message's title and body exactly as sent. So the server picks each device's
language and sends that device the matching text. Pass a `LocalizedMessage` instead of an `ExpoMessage`:

```php
use M0d1xd\ExpoNotification\LocalizedMessage;

// Written out per language
PushNotifier::send(LocalizedMessage::make([
    'en' => ExpoMessage::create('New bid', 'Ali bid $500')->data(['url' => '/ads/7']),
    'ar' => ExpoMessage::create('مزايدة جديدة', 'علي زايد بـ 500$')->data(['url' => '/ads/7']),
])->fallback('en'), $users);

// Or built per language, e.g. from translation files or your own templates; only the languages needed are built
PushNotifier::send(LocalizedMessage::using(
    fn (string $locale) => ExpoMessage::create(__('push.bid.title', [], $locale), __('push.bid.body', ['name' => 'Ali'], $locale)),
), $users);
```

Each device's language is, in order:

1. the owner's `preferredLocale()`, when the owner model implements Laravel's
   `Illuminate\Contracts\Translation\HasLocalePreference` (also used by Laravel mail);
2. the `locale` the device registered with (expo-notifications-client sends the phone's language);
3. the fallback: `->fallback()`, else `locales.fallback`, else `app.fallback_locale`.

`ar-SA` and `ar_EG` match `ar` when there is no exact match. Guests have no owner, so they get their device's language.

```php
// app/Models/User.php
class User extends Authenticatable implements HasLocalePreference
{
    public function preferredLocale(): ?string
    {
        return $this->preferred_language; // 'en' / 'ar'
    }
}
```

```php
// config/expo-notification.php
'locales' => [
    'supported' => ['en', 'ar'],  // the languages LocalizedMessage::using() builds
    'fallback' => env('EXPO_NOTIFICATION_FALLBACK_LOCALE'), // null = app.fallback_locale
],
```

It is still one notification: `title`/`body` hold the fallback language's text, `translations` holds every language
that was sent, and each delivery records its `locale`. In the Filament dashboard, `->locales(['en' => 'English', 'ar' =>
'العربية'])` adds a tab per language to **Send notification**.

> Upgrading from 0.1.x: run `php artisan expo-notification:install` again (it publishes only the new
> `add_locale_to_push_notifications` migration), then `php artisan migrate`.

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

## Filament dashboard

With [Filament](https://filamentphp.com) 5, add the plugin to a panel to get a **Push notifications** section:

- **Notifications**: every notification with targeted, delivered, opened and failed counts and rates; per device, the
  Expo ticket and receipt, delivered/opened times and the raw device events;
- **Devices**: registered devices with their owner (or Guest), with send-test and deactivate actions;
- a **Send notification** action (to all active devices, chosen users or chosen devices);
- dashboard widgets: the last 7 days' totals and a 14-day chart.

```php
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(ExpoNotificationPlugin::make());
}
```

Every option is optional:

```php
ExpoNotificationPlugin::make()
    ->navigationGroup('Notifications Center')   // default "Push notifications"; null = no group
    ->navigationSort(10)                        // the second item follows the first
    ->notificationsResource(label: 'Sent', icon: Heroicon::OutlinedMegaphone)
    ->devicesResource(label: 'Phones', enabled: true)
    ->widgets([PushStatsOverview::class])       // default both widgets; [] = none
    ->sending(false)                            // hide every Send action
    ->ownerModel(User::class, 'email')          // listed under "Chosen users", shown as the owner
    ->locales(['en' => 'English', 'ar' => 'العربية']) // a title/body tab per language; the first is the fallback
    ->pollingInterval('30s');                   // null = no auto-refresh
```

`ownerModel()` defaults to `owner.model` in the `foreign` owner mode. In `morph` mode, without it, the
**Chosen users** target is hidden. The widgets (`M0d1xd\ExpoNotification\Filament\Widgets\*`) are ordinary Filament
widgets, so you can also put them on any page yourself.

The pages don't check permissions themselves. Restrict them with policies for
`M0d1xd\ExpoNotification\Models\PushNotification` and `PushToken`, as for any Filament resource.

### Renaming and translating labels

Every label (group, items, columns, filters, actions, widgets) is a translation string:

```bash
php artisan vendor:publish --tag=expo-notification-translations
```

Edit `lang/vendor/expo-notification/en/filament.php`, or copy it to another locale (`ar/filament.php`). A published
file only needs the keys you change.

### Taking over the code

To change more than the options allow, copy the section into your app:

```bash
php artisan expo-notification:filament-publish   # to app/Filament/ExpoNotification, namespace App\Filament\ExpoNotification
```

Then register `App\Filament\ExpoNotification\ExpoNotificationPlugin` instead of the package's. It has the same
options. The copy is yours: package updates no longer reach it. Files that already exist are kept unless you pass
`--force`.

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
| `push_notifications` | Each notification sent (ULID id, also sent to devices as `data.notificationId`); `translations` for multi-language sends |
| `push_notification_deliveries` | Per notification per device: `locale`, Expo ticket/receipt, `delivered_at`, `opened_at`, `dismissed_at` |
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

### Table names

The migrations read `tables` (and `owner`) from the config when `php artisan migrate` runs, so set them before the
first migrate:

```php
// config/expo-notification.php
'tables' => [
    'push_tokens' => 'app_devices',
    'notifications' => 'app_push_messages',
    'deliveries' => 'app_push_deliveries',
    'events' => 'app_push_events',
],
```

Foreign keys and the models follow the same names. If the config is cached, run `php artisan config:clear` first.

Changing a name **after** migrating does not rename anything: the models would look for a table that doesn't exist,
and a rollback would drop the wrong one. Rename the table in a migration of your own
(`Schema::rename('push_tokens', 'app_devices')`), then change the config.

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
