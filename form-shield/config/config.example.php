<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__, 2);

$dotenv = Dotenv::createImmutable($projectRoot);
$dotenv->safeLoad();

return [
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'dbname' => $_ENV['DB_NAME'] ?? 'form_shield',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
    ],

    'security' => [
        'ip_hash_secret' => $_ENV['IP_HASH_SECRET'] ?? '',
        'ip_encryption_key' => $_ENV['IP_ENCRYPTION_KEY'] ?? '',
    ],

    'geoip' => [
        'enabled' => true,
    ],

    'phone' => [
        'home_country' => 'EE',
    ],

    'ai' => [
        'enabled' => filter_var(
            $_ENV['AI_ENABLED'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        ),

        'api_key' => $_ENV['OPENAI_API_KEY'] ?? '',

        'model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-5.6-luna',
    ],
];