<?php
declare(strict_types=1);

// Usage:
//   php tests/handover_cli.php schedule CLAIM ACTOR "2026-12-01T10:30"
//   php tests/handover_cli.php complete CLAIM ACTOR 1|0 "notes"
//   php tests/handover_cli.php revoke   CLAIM ACTOR "reason"
if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

[$script, $action, $claim, $actor] = array_pad($argv, 4, '');
$claim = (int) $claim;
$actor = (int) $actor;

$result = match ($action) {
    'schedule' => handover_schedule($claim, $actor, (string) ($argv[4] ?? '')),
    'complete' => handover_complete($claim, $actor, ($argv[4] ?? '0') === '1', (string) ($argv[5] ?? '')),
    'revoke'   => handover_revoke($claim, $actor, (string) ($argv[4] ?? '')),
    default    => ['ok' => false, 'message' => 'Unknown action.'],
};
echo ($result['ok'] ? 'OK' : 'REFUSED') . ': ' . $result['message'] . PHP_EOL;