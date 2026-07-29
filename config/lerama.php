<?php

return [
    'languages' => [
        'pt_BR' => 'Português (Brasil)',
        'en' => 'English',
        'es' => 'Español',
    ],

    'admin' => [
        // Also the login for the Filament panel at /admin.
        'username' => env('ADMIN_USERNAME', 'admin'),
        'password' => env('ADMIN_PASSWORD', ''),
        'email' => env('ADMIN_EMAIL', ''),
    ],

    'proxy' => [
        'urls' => env('PROXY_URL', ''),
        'ssl_verify' => env('PROXY_SSL_VERIFY', true),
    ],

    'feeds' => [
        'max_per_run' => (int) env('FEED_MAX_PER_RUN', 3),
        'item_error_threshold' => (int) env('FEED_ITEM_ERROR_THRESHOLD', 5),
        'subscriber_show_post' => env('SUBSCRIBER_SHOW_POST', false),
        'fetch_interval_success' => 86400,
        'fetch_interval_not_modified' => 86400,
        'fetch_interval_error' => 3600,
        'max_items_per_run' => 100,
        'max_pages_per_run' => 5,
    ],

    'random_post_days' => (int) env('RANDOM_POST_DAYS', 30),

    'items_per_page' => (int) env('ITEMS_PER_PAGE', 21),

    'image_extract_batch_size' => (int) env('IMAGE_EXTRACT_BATCH_SIZE', 50),

    'content_check_batch_size' => (int) env('CONTENT_CHECK_BATCH_SIZE', 500),

    'cache' => [
        'warm_feeds_limit' => (int) env('CACHE_WARM_FEEDS_LIMIT', 10),
    ],

];
