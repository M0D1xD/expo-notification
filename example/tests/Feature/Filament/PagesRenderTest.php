<?php

namespace Tests\Feature\Filament;

use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\PushNotificationResource;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;

/** The screens come from the package's ExpoNotificationPlugin; its own tests cover their behaviour. */
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
            $this->get($url)->assertOk()->assertSee('Push notifications');
        }
    }
}
