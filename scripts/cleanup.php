<?php
declare(strict_types=1);

// Run from the terminal:  php scripts/cleanup.php
if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

echo 'Expired rate-limit rows deleted: ' . rate_cleanup() . PHP_EOL;