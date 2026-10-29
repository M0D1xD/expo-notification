<?php

// Labels of the Filament section (ExpoNotificationPlugin). Publish with
// `php artisan vendor:publish --tag=expo-notification-translations` to rename or translate them.

return [

    'navigation' => [
        'group' => 'Push notifications',
    ],

    'notifications' => [
        'navigation_label' => 'Notifications',
        'model_label' => 'notification',
        'plural_model_label' => 'notifications',
        'columns' => [
            'sent_at' => 'Sent at',
            'title' => 'Title',
            'targeted' => 'Targeted',
            'delivered' => 'Delivered',
            'opened' => 'Opened',
            'failed' => 'Failed',
        ],
        'filters' => [
            'sent_at' => 'Sent at',
            'from' => 'From',
            'until' => 'Until',
            'has_failures' => 'Has failures',
        ],
        'infolist' => [
            'section' => 'Notification',
            'title' => 'Title',
            'sent_at' => 'Sent at',
            'body' => 'Body',
            'id' => 'Notification ID',
            'data' => 'Data',
            'payload' => 'Payload as sent',
            'payload_description' => 'The message before the per-platform tracking payload (data-only on Android).',
        ],
        'deliveries' => 'Deliveries',
        'events' => 'Events',
    ],

    'devices' => [
        'navigation_label' => 'Devices',
        'model_label' => 'device',
        'plural_model_label' => 'devices',
        'columns' => [
            'device' => 'Device',
            'platform' => 'Platform',
            'locale' => 'Locale',
            'owner' => 'Owner',
            'active' => 'Active',
            'last_registered_at' => 'Last registered',
            'deactivated_at' => 'Deactivated at',
            'push_token' => 'Expo push token',
        ],
        'filters' => [
            'all' => 'All devices',
            'signed_in' => 'Signed-in user',
            'guest' => 'Guest',
        ],
        'section' => 'Device',
        'deliveries' => 'Notifications',
    ],

    'deliveries' => [
        'device' => 'Device',
        'deleted_device' => 'Deleted device',
        'platform' => 'Platform',
        'owner' => 'Owner',
        'notification' => 'Notification',
        'ticket' => 'Ticket',
        'receipt' => 'Receipt',
        'pending' => 'pending',
        'error' => 'Error',
        'sent_at' => 'Sent at',
        'delivered_at' => 'Delivered at',
        'opened_at' => 'Opened at',
        'dismissed_at' => 'Dismissed at',
        'delivered' => 'Delivered',
        'opened' => 'Opened',
        'failed' => 'Failed',
    ],

    'events' => [
        'occurred_at' => 'Occurred at',
        'device' => 'Device',
        'type' => 'Type',
        'source' => 'Source',
        'app_state' => 'App state',
        'types' => [
            'received' => 'Received',
            'opened' => 'Opened',
            'dismissed' => 'Dismissed',
        ],
    ],

    'platforms' => [
        'android' => 'Android',
        'ios' => 'iOS',
    ],

    'owner' => [
        'guest' => 'Guest',
    ],

    'stats' => [
        'targeted' => 'Targeted',
        'accepted' => ':count accepted by Expo',
        'delivered' => 'Delivered',
        'delivered_description' => ':rate of targeted, reported by the device',
        'opened' => 'Opened',
        'opened_description' => ':rate of delivered',
        'failed' => 'Failed',
        'failed_description' => 'Ticket or receipt error',
    ],

    'widgets' => [
        'overview_heading' => 'Push, last :days days',
        'notifications_sent' => 'Notifications sent',
        'active_devices' => 'Active devices',
        'no_devices' => 'None registered',
        'chart_heading' => 'Deliveries, last :days days',
    ],

    'actions' => [
        'send' => [
            'label' => 'Send notification',
            'test_label' => 'Send test notification',
            'title' => 'Title',
            'body' => 'Body',
            'url' => 'Deep link',
            'url_helper' => 'Sent as data.url, e.g. /details',
            'target' => 'Send to',
            'targets' => [
                'all' => 'All active devices',
                'users' => 'Chosen users',
                'devices' => 'Chosen devices',
            ],
            'users' => 'Users',
            'devices' => 'Devices',
            'none' => 'No active devices to send to',
            'sent' => 'Sent to :count device|Sent to :count devices',
        ],
        'deactivate' => [
            'label' => 'Deactivate',
            'description' => 'The device stops receiving pushes until the app registers again.',
            'bulk_description' => 'The devices stop receiving pushes until the app registers again.',
            'done' => 'Device deactivated',
            'bulk_done' => 'Devices deactivated',
        ],
    ],

];
