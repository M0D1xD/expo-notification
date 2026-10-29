<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PushNotifications\PushNotificationResource;
use App\Filament\Resources\PushTokens\PushTokenResource;

class PagesRenderTest extends FilamentTestCase
{
    public function test_every_push_page_renders_through_the_panel(): void
    {
        // Without a FilamentUser model, Filament only lets users in on the local environment.
        config(['app.env' => 'local']);
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
