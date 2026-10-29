<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use M0d1xd\ExpoNotification\EventRecorder;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushToken;
use M0d1xd\ExpoNotification\PushTokenRegistry;

/**
 * Demo data for the dashboard without phones or Expo: devices, two weeks of notifications with mixed outcomes, and
 * the device events. Expo is never called; the tokens are made up.
 */
class PushDemoSeeder extends Seeder
{
    public function run(PushTokenRegistry $registry, EventRecorder $recorder): void
    {
        $users = User::factory(4)->create();
        $devices = collect(range(1, 12))->map(fn (int $i) => $registry->register([
            'device_id' => 'demo-'.Str::lower(Str::random(12)),
            'push_token' => 'ExponentPushToken['.Str::random(22).']',
            'platform' => $i % 3 === 0 ? 'ios' : 'android',
            'locale' => $i % 4 === 0 ? 'ar' : 'en',
        ], $i <= 8 ? $users[$i % 4] : null));

        $titles = ['Your order shipped', 'New message', 'Weekly digest', 'Price drop', 'Reminder', 'Welcome back'];

        foreach (range(13, 0) as $daysAgo) {
            foreach (range(1, random_int(1, 3)) as $n) {
                $this->notification($devices->random(random_int(4, 12)), now()->subDays($daysAgo)->setTime(random_int(8, 20), random_int(0, 59)), $titles[array_rand($titles)], $recorder);
            }
        }
    }

    /** @param  Collection<int, PushToken>  $devices */
    private function notification($devices, $sentAt, string $title, EventRecorder $recorder): void
    {
        $notification = PushNotification::query()->create([
            'title' => $title,
            'body' => 'Demo notification',
            'data' => ['url' => '/details'],
            'payload' => ['title' => $title, 'body' => 'Demo notification', 'data' => ['url' => '/details']],
            'sent_at' => $sentAt,
        ]);

        foreach ($devices as $device) {
            $rejected = random_int(1, 20) === 1;
            $notification->deliveries()->create([
                'push_token_id' => $device->id,
                'push_token' => $device->push_token,
                'ticket_id' => $rejected ? null : (string) Str::uuid(),
                'ticket_status' => $rejected ? 'error' : 'ok',
                'receipt_status' => $rejected ? null : 'ok',
                'error_type' => $rejected ? 'DeviceNotRegistered' : null,
                'error_message' => $rejected ? 'The device cannot receive push notifications anymore' : null,
                'sent_at' => $sentAt,
                'receipt_checked_at' => $rejected ? null : $sentAt->copy()->addMinutes(15),
            ]);

            $outcome = random_int(1, 10);
            if ($rejected || $outcome <= 2) {
                continue; // never arrived (or force-stopped)
            }

            $events = [['notification_id' => $notification->id, 'type' => 'received', 'source' => 'task', 'app_state' => 'background', 'occurred_at' => $sentAt->copy()->addSeconds(random_int(1, 30))->toIso8601String()]];
            if ($outcome >= 7) {
                $events[] = ['notification_id' => $notification->id, 'type' => 'opened', 'source' => 'listener', 'app_state' => 'background', 'occurred_at' => $sentAt->copy()->addMinutes(random_int(1, 120))->toIso8601String()];
            }
            $recorder->record($device, $events);
        }
    }
}
