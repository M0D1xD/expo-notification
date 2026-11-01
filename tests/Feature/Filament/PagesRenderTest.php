<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature\Filament;

use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\PushNotificationResource;
use M0d1xd\ExpoNotification\Filament\Resources\PushTokens\PushTokenResource;
use M0d1xd\ExpoNotification\Filament\Support\OwnerKey;
use M0d1xd\ExpoNotification\Tests\Fixtures\User;

class PagesRenderTest extends FilamentTestCase
{
    public function test_every_push_page_renders_through_the_panel(): void
    {
        [$notification, $devices] = $this->sendMixed();
        $owned = $this->device('owned', User::make('Rana'));

        foreach ([
            '/admin',
            PushNotificationResource::getUrl('index'),
            PushNotificationResource::getUrl('view', ['record' => $notification]),
            PushTokenResource::getUrl('index'),
            PushTokenResource::getUrl('view', ['record' => $devices['a']]),
            PushTokenResource::getUrl('owner', ['owner' => OwnerKey::encode(OwnerKey::fromDevice($owned))]),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
