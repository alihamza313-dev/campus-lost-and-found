<?php
declare(strict_types=1);

// Usage: php tests/approve_cli.php CLAIM_ID REVIEWER_USER_ID
if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$result = claim_approve((int) ($argv[1] ?? 0), (int) ($argv[2] ?? 0), 'CLI concurrency test', false);
echo ($result['ok'] ? 'APPROVED' : 'REFUSED') . ': ' . $result['message'] . PHP_EOL;