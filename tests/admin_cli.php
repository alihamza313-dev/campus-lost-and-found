<?php
declare(strict_types=1);

// Usage:
//   php tests/admin_cli.php suspend    TARGET ACTOR "reason" DAYS(0|7|30|90)
//   php tests/admin_cli.php reactivate TARGET ACTOR
//   php tests/admin_cli.php role       TARGET ACTOR student|custodian|admin
if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$action = $argv[1] ?? '';
$target = (int) ($argv[2] ?? 0);
$actor  = (int) ($argv[3] ?? 0);

$result = match ($action) {
    'suspend'    => admin_suspend_user($target, $actor, (string) ($argv[4] ?? ''), (int) ($argv[5] ?? 7)),
    'reactivate' => admin_reactivate_user($target, $actor),
    'role'       => admin_change_role($target, $actor, (string) ($argv[4] ?? '')),
    default      => ['ok' => false, 'message' => 'Unknown action.'],
};
echo ($result['ok'] ? 'OK' : 'REFUSED') . ': ' . $result['message'] . PHP_EOL;