<?php
declare(strict_types=1);

/** SQL expression for the start of the current fixed time window, computed by MySQL. */
function rate_window_sql(int $window): string
{
    return sprintf('FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP() / %d) * %d)', $window, $window);
}

/** Hits recorded so far in the current window for this key (read-only). */
function rate_count(string $key, int $window): int
{
    $stmt = db()->prepare(
        'SELECT hit_count FROM rate_limits WHERE limit_key = ? AND window_start = ' . rate_window_sql($window)
    );
    $stmt->execute([$key]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

/** Records one hit and returns the new count. */
function rate_record(string $key, int $window): int
{
    $stmt = db()->prepare(
        'INSERT INTO rate_limits (limit_key, window_start) VALUES (?, ' . rate_window_sql($window) . ')
         ON DUPLICATE KEY UPDATE hit_count = hit_count + 1'
    );
    $stmt->execute([$key]);
    if (random_int(1, 200) === 1) {      // housekeeping: about 1 write in 200 also purges old rows
        rate_cleanup();
    }
    return rate_count($key, $window);
}

/** True if the key has already used up its limit (limit names come from config/limits.php). */
function rate_limited(string $limitName, string $key): bool
{
    $cfg = config("limits.$limitName");
    return rate_count($key, (int) $cfg['window']) >= (int) $cfg['max'];
}

/** Records a hit for this limit. */
function rate_hit(string $limitName, string $key): int
{
    $cfg = config("limits.$limitName");
    return rate_record($key, (int) $cfg['window']);
}

/** Records a hit and returns true if it was still within the limit. */
function rate_allow(string $limitName, string $key): bool
{
    $cfg = config("limits.$limitName");
    return rate_record($key, (int) $cfg['window']) <= (int) $cfg['max'];
}

/** Deletes counters whose window ended more than 2 days ago (the longest window is 1 day). Returns rows deleted. */
function rate_cleanup(): int
{
    $stmt = db()->prepare('DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL 2 DAY');
    $stmt->execute();
    return $stmt->rowCount();
}