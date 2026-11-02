<?php
/**
 * Mobi Boost — API configuration.
 *
 * This file is tracked in Git, so never write real credentials into it.
 * Keep the defaults below for local development and supply the production
 * values through server environment variables (cPanel/hPanel → Environment
 * Variables or a .htaccess SetEnv block):
 *
 *   MYSQL_HOST, MYSQL_PORT, MYSQL_DATABASE, MYSQL_USER, MYSQL_PASSWORD, MYSQL_TABLE_PREFIX
 *   JWT_SECRET   — long random string, MUST be changed in production
 *   APP_URL      — e.g. https://mobiboost.store (no trailing slash)
 *   CORS_ORIGIN  — e.g. https://mobiboost.store
 */
declare(strict_types=1);

return [
    // MongoDB Atlas connection string, e.g.
    // 'mongodb+srv://<username>:<password>@cluster0.xxxxx.mongodb.net'
    'mongoUri' => getenv('MONGO_URI') ?: 'mongodb+srv://mobiuser:ChangeMe123@cluster0.example.mongodb.net',

    // Database name in Atlas
    'dbName' => getenv('MONGO_DB') ?: 'mobi_boost',

    // MySQL connection — this is the live storage layer (see api-mysql/README.md).
    // Production credentials come from the MYSQL_* environment variables; the
    // fallbacks are the local XAMPP defaults only.
    'mysql' => [
        'host' => getenv('MYSQL_HOST') ?: 'localhost',
        'port' => (int)(getenv('MYSQL_PORT') ?: 3306),
        'database' => getenv('MYSQL_DATABASE') ?: 'mobi_boost',
        'username' => getenv('MYSQL_USER') ?: 'root',
        // Never commit a real password here — set MYSQL_PASSWORD on the server.
        'password' => getenv('MYSQL_PASSWORD') ?: '',
        'tablePrefix' => getenv('MYSQL_TABLE_PREFIX') ?: 'mb_',
    ],

    // Secret used to sign admin tokens and order-confirmation links.
    // MUST be overridden with a long random value in production: the default
    // below is public, so leaving it allows forged admin sessions.
    'jwtSecret' => getenv('JWT_SECRET') ?: 'REPLACE_WITH_A_LONG_RANDOM_SECRET_STRING',

    // Shop identity
    'currencySymbol' => '$',
    'currencyCode' => 'USD',

    // Public site URL used to build the QR links printed on shipping labels.
    // Leave empty to derive it from the admin request; set it in production,
    // e.g. 'https://mobiboost.com' (no trailing slash).
    'appUrl' => getenv('APP_URL') ?: '',

    // CORS — set to your shop domain in production, e.g. 'https://mobiboost.com'
    'corsOrigin' => getenv('CORS_ORIGIN') ?: '*',

    // File uploads (product images / ad media)
    'uploadDir' => __DIR__ . '/../uploads',
    'uploadUrl' => '/uploads',
    'maxUploadBytes' => 8 * 1024 * 1024,

    // Catalog pagination
    'pageSize' => 24,
];
