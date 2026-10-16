<?php
/**
 * Mobi Boost — configuration TEMPLATE (safe to commit, contains no secrets).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHERE THIS FILE GOES IN PRODUCTION (hosting → Hostinger)
 * ─────────────────────────────────────────────────────────────────────────────
 * Put the real file OUTSIDE the web root, one level above public_html:
 *
 *     /home/uXXXXXXXX/mobi-private/config.php        ← the real credentials
 *     /home/uXXXXXXXX/public_html/api/_config.php    ← loads it automatically
 *
 * api/_config.php finds it on its own — there is nothing to configure. Because
 * the file is outside public_html, a deploy that replaces the whole web root
 * (or a manual "clear public_html and redeploy") cannot overwrite or delete it.
 *
 * For LOCAL development (XAMPP), just copy this file to api/config.php and fill
 * in your local MySQL values.
 *
 * NEVER commit the filled-in file. api/config.php is git-ignored, and the deploy
 * artifact deliberately excludes it (see .github/workflows/deploy.yml and
 * HOSTINGER-GIT-DEPLOY.md).
 *
 * Every value below can also be supplied as an environment variable — useful if
 * your host exposes a "Environment variables" panel. The variable wins over the
 * literal value (getenv(...) ?: 'literal'), so you can commit this file as-is.
 */
declare(strict_types=1);

return [
    // MongoDB Atlas connection string. Legacy: MySQL is the live storage layer.
    'mongoUri' => getenv('MONGO_URI') ?: 'mongodb+srv://mobiuser:ChangeMe123@cluster0.example.mongodb.net',

    // Database name in Atlas (legacy).
    'dbName' => getenv('MONGO_DB') ?: 'mobi_boost',

    // ---- MySQL — the live storage layer ----------------------------------
    // On Hostinger the values come from hPanel → Databases → MySQL Databases.
    // The database/user are prefixed with your account id (e.g. u118872087_mobi).
    'mysql' => [
        'host' => getenv('MYSQL_HOST') ?: 'localhost',
        'port' => (int)(getenv('MYSQL_PORT') ?: 3306),
        'database' => getenv('MYSQL_DATABASE') ?: 'CHANGE_ME_database',
        'username' => getenv('MYSQL_USER') ?: 'CHANGE_ME_user',
        'password' => getenv('MYSQL_PASSWORD') ?: 'CHANGE_ME_password',
        'tablePrefix' => getenv('MYSQL_TABLE_PREFIX') ?: 'mb_',
    ],

    // Secret used to sign admin tokens. Replace with a LONG RANDOM string
    // (e.g. 64 hex characters). Changing it signs every admin out.
    'jwtSecret' => getenv('JWT_SECRET') ?: 'CHANGE_ME_long_random_secret',

    // Shop identity.
    'currencySymbol' => '$',
    'currencyCode' => 'USD',

    // CORS — lock this to your domain in production, e.g. 'https://mobiboost.store'.
    'corsOrigin' => getenv('CORS_ORIGIN') ?: '*',

    // Catalog pagination.
    'pageSize' => 24,

    // NOTE: uploadDir / uploadUrl are intentionally NOT set here. api/_config.php
    // always computes them as <web-root>/uploads → /uploads, so they stay correct
    // even when this file lives outside the web root. Override with the
    // MOBI_UPLOAD_DIR / MOBI_UPLOAD_URL environment variables if ever needed.
];
