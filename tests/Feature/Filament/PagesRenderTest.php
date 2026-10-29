<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature\Filament;

use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\PushNotificationResource;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;

class PagesRenderTest extends FilamentTestCase
{
    public function test_every_push_page_renders_through_the_panel(): void
    {
        [$notification, $devices] = $this->sendMixed();

        foreach ([
            '/admin',
            PushNotificationResource::getUrl('index'),
            PushNotificationResource::getUrl('view', ['record' => $notification]),
            PushTokenResource::getUrl('index'),
            PushTokenResource::getUrl('view', ['record' => $devices['a']]),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
