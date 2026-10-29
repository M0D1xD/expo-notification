# Example: Laravel + Filament dashboard

A Laravel 13 app with Filament 5 that uses `m0d1xd/expo-notification` from the parent folder:

- the package's device routes, for the [client example app](https://github.com/M0D1xD/expo-notification-client/tree/main/example);
- **Push → Notifications**: every notification, with targeted, delivered and opened counts and rates; per device, the
  Expo ticket and receipt, delivered/opened times and the raw device events;
- **Push → Devices**: registered devices with their owner (or Guest), with send-test and deactivate actions;
- **Dashboard**: the last 7 days' totals and a 14-day chart;
- a **Send notification** action (to all active devices, chosen users or chosen devices).

The dashboard only reads the package's tables through its API (`withDeliveryStats()`, `DeliveryStats`); it is not
part of the package.

## Run it

PHP 8.3+ and Composer.

```bash
cd example
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed            # SQLite; creates admin@example.com / password
php artisan db:seed --class=PushDemoSeeder   # optional: two weeks of demo data, no phones needed
php artisan serve --host 0.0.0.0 --port 8000
```

Open http://localhost:8000/admin.

## With a phone

1. Build the [client example](https://github.com/M0D1xD/expo-notification-client/tree/main/example) with
   `EXPO_PUBLIC_API_URL=http://<this machine's LAN IP>:8000`. The phone registers on launch and shows up under
   **Devices**.
2. **Notifications → Send notification**. The notification page refreshes every 10 seconds: ticket `ok`, then
   delivered when the phone reports it, then opened when you tap it.
3. Receipts are fetched 15 minutes after sending by the scheduler (`php artisan schedule:work`), or right away with
   `php artisan expo-notification:check-receipts --min-age=0`.

Devices sign in as a guest: the example app has no login. Assign an owner in tinker
(`$token->owner()->associate($user)->save()`) to try sending to chosen users.

## Tests

```bash
php artisan test
```

The tests fake Expo (`Http::fake`) and build data through the package, as the phones do.
