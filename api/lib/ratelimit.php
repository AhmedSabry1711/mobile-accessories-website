<?php
/**
 * Mobi Boost — tiny file-based rate limiter.
 *
 * Used by the public order-confirmation endpoint so a QR code cannot be
 * brute-forced. Counters live in the system temp directory (one hashed file per
 * bucket), which works on Hostinger without extra database tables or cron jobs.
 * Failures fail open — a broken temp folder must never take checkout down.
 */
declare(strict_types=1);

/**
 * Count a hit for $key and abort with HTTP 429 once $max hits are reached in
 * the rolling $windowSeconds window.
 */
function rate_limit(string $key, int $max, int $windowSeconds): void {
    $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'mobi_boost_rate';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return;
    }
    $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    $now = time();

    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return;
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return;
    }

    $raw = stream_get_contents($handle);
    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if (
        !is_array($data)
        || !isset($data['start'], $data['hits'])
        || $now - (int)$data['start'] >= $windowSeconds
    ) {
        $data = ['start' => $now, 'hits' => 0];
    }
    $data['hits'] = (int)$data['hits'] + 1;

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    // Occasionally drop counters nobody used for a day.
    if (random_int(1, 50) === 1) {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $stale) {
            if (@filemtime($stale) < $now - 86400) {
                @unlink($stale);
            }
        }
    }

    if ($data['hits'] > $max) {
        $retry = max(1, (int)$data['start'] + $windowSeconds - $now);
        header('Retry-After: ' . $retry);
        fail('Too many attempts. Please try again later.', 429);
    }
}
