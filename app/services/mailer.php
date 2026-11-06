<?php
declare(strict_types=1);

/**
 * Development mailer: writes the email to storage/logs/mail.log instead of sending it.
 * In production (Module 11) this function is replaced by real SMTP delivery.
 */
function send_mail(string $to, string $subject, string $body): void
{
    $entry = str_repeat('=', 60) . PHP_EOL
        . 'Date:    ' . date('Y-m-d H:i:s') . PHP_EOL
        . 'To:      ' . $to . PHP_EOL
        . 'Subject: ' . $subject . PHP_EOL . PHP_EOL
        . $body . PHP_EOL . PHP_EOL;

    file_put_contents(BASE_PATH . '/storage/logs/mail.log', $entry, FILE_APPEND | LOCK_EX);
}