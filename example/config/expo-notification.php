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
