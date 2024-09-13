<?php
return [
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'dbname' => getenv('DB_NAME') ?: 'lerama',
        'charset' => 'utf8mb4'
    ],
    'admin_password' => getenv('ADMIN_PASSWORD') ?: 'admin_pass',
    'site' => [
        'url' => getenv('SITE_URL') ?: 'https://lerama.test',
        'name' => getenv('SITE_NAME') ?: 'Lerama'
    ]
];
