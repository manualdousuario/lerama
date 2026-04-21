<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

return [
    'paths' => [
        'migrations' => __DIR__ . '/setup/migrations',
        'seeds' => __DIR__ . '/setup/seeds',
    ],
    'environments' => [
        'default_environment' => 'production',
        'production' => [
            'adapter' => 'mysql',
            'host' => $_ENV['LERAMA_DB_HOST'] ?? 'localhost',
            'name' => $_ENV['LERAMA_DB_NAME'] ?? '',
            'user' => $_ENV['LERAMA_DB_USER'] ?? '',
            'pass' => $_ENV['LERAMA_DB_PASS'] ?? '',
            'port' => (int)($_ENV['LERAMA_DB_PORT'] ?? 3306),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'version_order' => 'creation',
];
