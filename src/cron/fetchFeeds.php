<?php
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/../class/Database.php';
require_once __DIR__ . '/../class/FeedFetcher.php';

use Src\Database;
use Src\FeedFetcher;

$appConfig = require __DIR__ . '/../config/appConfig.php';
$feedsConfig = require __DIR__ . '/../config/feedsConfig.php';

try {
    $db = Database::getInstance($appConfig['database']);
    $feed_fetcher = new FeedFetcher($db, $feedsConfig);

    $feed_fetcher->fetchFeeds();
    echo "Feeds processados.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
