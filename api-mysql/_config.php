<?php
/**
 * Mobi Boost — runtime configuration resolver.
 *
 * The API must never depend on a credentials file that a deploy can delete or
 * overwrite. This resolver looks for the config array in a fixed order and the
 * first file it finds wins:
 *
 *   1. $MOBI_CONFIG
 *        Absolute path taken from the environment. Set it as a hosting
 *        environment variable, or from .htaccess with
 *        `SetEnv MOBI_CONFIG /home/<account>/mobi-private/config.php`.
 *
 *   2. <account-home>/mobi-private/config.php        ← RECOMMENDED on Hostinger
 *        From this file: __DIR__ is `<web-root>/api`, so dirname(__DIR__, 2)
 *        is the account home. The file therefore sits one level ABOVE
 *        public_html. A deploy that replaces the entire web root — or a manual
 *        "clear public_html and redeploy" — cannot touch it.
 *
 *   3. <web-root>/api/config.php
 *        The in-place file. Used for local development (XAMPP) and compatible
 *        with installs created before this resolver existed.
 *
 * If none of them exists the caller gets an explicit exception instead of a
 * half-booted API, so the failure is reported rather than guessed at.
 *
 * NOTE: this file is mirrored verbatim in api-mysql/ — keep both copies in sync.
 */
declare(strict_types=1);

if (!function_exists('mobi_config')) {
    /**
     * @return array{0: array, 1: string} The config array and the path it came from.
     */
    function mobi_config_locate(): array
    {
        $candidates = [];

        $env = getenv('MOBI_CONFIG');
        if (is_string($env) && trim($env) !== '') {
            $candidates[] = $env;
        }

        // __DIR__ = <web-root>/api  ->  dirname(__DIR__, 2) = <account home>
        $candidates[] = dirname(__DIR__, 2) . '/mobi-private/config.php';
        $candidates[] = __DIR__ . '/config.php';

        foreach ($candidates as $path) {
            if (is_file($path)) {
                // The credentials file must only return an array. Editing it with
                // hPanel's File Manager can silently add a UTF-8 BOM or trailing
                // whitespace, and that stray byte is sent before our headers —
                // which breaks every JSON response ("headers already sent" plus a
                // BOM glued to the body). Swallow any output from the include.
                ob_start();
                /** @var mixed $config */
                $config = require $path;
                ob_end_clean();

                if (is_array($config)) {
                    return [$config, $path];
                }
            }
        }

        throw new RuntimeException(
            'No configuration file found. Looked in: ' . implode(' | ', $candidates)
            . '. Copy api/config.example.php to <account-home>/mobi-private/config.php '
            . 'and fill in the MySQL credentials.'
        );
    }

    /** The configuration array, or an exception explaining what is missing. */
    function mobi_config(): array
    {
        [$config] = mobi_config_locate();

        // Storage paths are an infrastructure concern, not a credential, so the
        // bootstrap owns them. This matters because the config file may live
        // outside the web root — in that case a __DIR__-relative default would
        // silently point at the wrong folder and every product image would 404.
        // `_config.php` always sits in <web-root>/api, so dirname(__DIR__) is
        // the web root no matter where the credentials file was loaded from.
        $webRoot = dirname(__DIR__);
        $config['uploadDir'] = getenv('MOBI_UPLOAD_DIR') ?: $webRoot . '/uploads';
        $config['uploadUrl'] = getenv('MOBI_UPLOAD_URL') ?: '/uploads';

        return $config;
    }

    /** Where the active configuration was loaded from (for /api/health.php). */
    function mobi_config_path(): string
    {
        return mobi_config_locate()[1];
    }
}
