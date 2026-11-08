<?php
declare(strict_types=1);

// Usage:
//   php tests/moderation_cli.php review         REPORT ACTOR
//   php tests/moderation_cli.php dismiss        REPORT ACTOR "notes"
//   php tests/moderation_cli.php resolve        REPORT ACTOR "notes"
//   php tests/moderation_cli.php remove-item    ITEM   ACTOR "reason" [REPORT]
//   php tests/moderation_cli.php remove-message MSG    ACTOR "reason" [REPORT]
//   php tests/moderation_cli.php suspend        USER   ACTOR "reason" DAYS [REPORT]
if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$a = $argv[1] ?? '';
$n = (int) ($argv[2] ?? 0);
$actor = (int) ($argv[3] ?? 0);
$text = (string) ($argv[4] ?? '');

$result = match ($a) {
    'review'         => report_start_review($n, $actor),
    'dismiss'        => report_finish($n, $actor, 'dismissed', $text),
    'resolve'        => report_finish($n, $actor, 'resolved', $text),
    'remove-item'    => moderate_remove_item($n, $actor, $text, isset($argv[5]) ? (int) $argv[5] : null),
    'remove-message' => moderate_remove_message($n, $actor, $text, isset($argv[5]) ? (int) $argv[5] : null),
    'suspend'        => admin_suspend_user($n, $actor, $text, (int) ($argv[5] ?? 7), isset($argv[6]) ? (int) $argv[6] : null),
    default          => ['ok' => false, 'message' => 'Unknown action.'],
};
echo ($result['ok'] ? 'OK' : 'REFUSED') . ': ' . $result['message'] . PHP_EOL;