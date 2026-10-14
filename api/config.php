<?php
/**
 * Mobi Boost — API configuration.
 * Edit these values for production (or use environment variables).
 */
declare(strict_types=1);

return [
    // MongoDB Atlas connection string, e.g.
    // 'mongodb+srv://<username>:<password>@cluster0.xxxxx.mongodb.net'
    'mongoUri' => getenv('MONGO_URI') ?: 'mongodb+srv://mobiuser:ChangeMe123@cluster0.example.mongodb.net',

    // Database name in Atlas
    'dbName' => getenv('MONGO_DB') ?: 'mobi_boost',

    // MySQL connection — this is the live storage layer (see api-mysql/README.md).
    'mysql' => [
        'host' => getenv('MYSQL_HOST') ?: 'localhost',
        'port' => (int)(getenv('MYSQL_PORT') ?: 3306),
        'database' => getenv('MYSQL_DATABASE') ?: 'mobi_boost',
        'username' => getenv('MYSQL_USER') ?: 'root',
        'password' => getenv('MYSQL_PASSWORD') ?: '',
        'tablePrefix' => getenv('MYSQL_TABLE_PREFIX') ?: 'mb_',
    ],

    // Secret used to sign admin tokens — replace with a long random string
    'jwtSecret' => getenv('JWT_SECRET') ?: 'REPLACE_WITH_A_LONG_RANDOM_SECRET_STRING',

    // Shop identity
    'currencySymbol' => '$',
    'currencyCode' => 'USD',

    // CORS — set to your shop domain in production, e.g. 'https://mobiboost.com'
    'corsOrigin' => getenv('CORS_ORIGIN') ?: '*',

    // File uploads (product images / ad media)
    'uploadDir' => __DIR__ . '/../uploads',
    'uploadUrl' => '/uploads',
    'maxUploadBytes' => 8 * 1024 * 1024,

    // Catalog pagination
    'pageSize' => 24,
];
