<?php
if (php_sapi_name() !== 'cli') {
    die('Este script só pode ser executado a partir da linha de comando.');
}

require_once __DIR__ . '/../class/Database.php';
require_once __DIR__ . '/../class/FeedFetcher.php';

use Src\Database;
use Src\FeedFetcher;

$appConfig = require __DIR__ . '/../config.php';

echo "Processando rotinas [".date("Y-m-d H:i:s")."]".PHP_EOL;

try {
    $db = Database::getInstance($appConfig['database']);
    $feed_fetcher = new FeedFetcher($db);

    $feed_fetcher->fetchFeeds();
    echo "Feeds processados".PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() .PHP_EOL;
}
