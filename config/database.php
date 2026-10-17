<?php
declare(strict_types=1);
// Prefer environment variables on hosting, or copy this to database.local.php.
return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'vmu_club',
    'username' => getenv('DB_USER') ?: 'vmuuser',
    'password' => getenv('DB_PASSWORD') ?: '',
    'timezone' => 'America/Toronto',
];


