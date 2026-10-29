<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expo Push Service
    |--------------------------------------------------------------------------
    |
    | The access token is only required when "Enhanced Security for Push
    | Notifications" is enabled for the project on expo.dev.
    |
    */

    'access_token' => env('EXPO_ACCESS_TOKEN'),

    'base_url' => env('EXPO_PUSH_BASE_URL', 'https://exp.host/--/api/v2/push'),

    // Seconds before a request to the Expo Push Service times out.
    'timeout' => 30,

    // Extra attempts for rate-limited (429) or failed (5xx) requests.
    'retries' => 2,

    // Request bodies larger than this many bytes are gzip-compressed.
    'gzip_threshold' => 1024,

    /*
    |--------------------------------------------------------------------------
    | Device Owner
    |--------------------------------------------------------------------------
    |
    | Who a push token belongs to. The owner is always nullable, so guest
    | devices can register before anyone signs in.
    |
    | "morph":   owner_type + owner_id columns, any model can own a token.
    | "foreign": a plain foreign key column to the given model's table.
    |
    */

    'owner' => [
        'mode' => 'morph',
        // Key type of the owner model(s): "int", "uuid" or "ulid".
        'key_type' => 'int',
        // "foreign" mode only:
        'model' => 'App\\Models\\User',
        'column' => 'user_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Routes
    |--------------------------------------------------------------------------
    |
    | The endpoints the app calls to register, detach (logout) and deactivate
    | a device. They have no auth middleware so guests can register; the
    | owner is the user signed in on "guard" (null = the default guard,
    | e.g. "sanctum" for token-authenticated apps).
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'expo-notification',
        'middleware' => ['api', 'throttle:60,1'],
        'guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking
    |--------------------------------------------------------------------------
    |
    | When enabled, PushNotifier sends Android devices a data-only push that
    | the client SDK (expo-notification-client) shows itself, and iOS devices
    | a content-available push, so "received" can be recorded in the
    | background. Apps without the client SDK must disable this, or Android
    | users will not see the notification.
    |
    */

    'tracking' => [
        'enabled' => env('EXPO_NOTIFICATION_TRACKING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | Expo sends a message's text as given, so a LocalizedMessage is sent to
    | each device in its language: the owner's preferredLocale() (Laravel's
    | HasLocalePreference), else the locale the device registered with, else
    | the fallback. "supported" lists the languages LocalizedMessage::using()
    | builds; the fallback defaults to app.fallback_locale.
    |
    */

    'locales' => [
        'supported' => [],
        'fallback' => env('EXPO_NOTIFICATION_FALLBACK_LOCALE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipts
    |--------------------------------------------------------------------------
    |
    | expo-notification:check-receipts fetches receipts for tracked sends.
    | The package schedules it on "schedule" (a cron expression, or null
    | to schedule it yourself); it only runs if the app runs schedule:run.
    |
    */

    'receipts' => [
        // Minutes after sending before a receipt is fetched (Expo suggests ~15).
        'delay' => 15,
        // Hours after which a receipt Expo never returned is marked "expired".
        'expire_after' => 24,
        'schedule' => '*/15 * * * *',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'push_tokens' => 'push_tokens',
        'notifications' => 'push_notifications',
        'deliveries' => 'push_notification_deliveries',
        'events' => 'push_notification_events',
    ],

];
