<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$_ENV['SUBSCRIBER_SHOW_POST']       = 'false';
$_ENV['FEED_MAX_PER_RUN']           = '3';
$_ENV['FEED_ITEM_ERROR_THRESHOLD']  = '5';
$_ENV['SMTP_HOST']                  = '';
$_ENV['SMTP_PORT']                  = '';
$_ENV['ADMIN_EMAIL']                = '';
$_ENV['PROXY_URL']                  = '';
$_ENV['CACHE_ADMIN_TTL']            = '60';
$_ENV['CACHE_WARM_FEEDS_LIMIT']     = '10';
$_ENV['CACHE_AUTO_INVALIDATE']      = 'true';
