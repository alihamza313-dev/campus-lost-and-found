<?php
declare(strict_types=1);

// Run from the terminal only:  php scripts/create_admin.php
if (PHP_SAPI !== 'cli') {
    exit('This script can only be run from the command line.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

function ask(string $prompt, bool $hidden = false): string
{
    echo $prompt;
    if ($hidden) {
        system('stty -echo');
    }
    $line = trim((string) fgets(STDIN));
    if ($hidden) {
        system('stty echo');
        echo PHP_EOL;
    }
    return $line;
}

$email = strtolower(ask('Admin email: '));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    exit("Invalid email.\n");
}

$pdo      = db();
$existing = user_find_by_email($email);

if ($existing) {
    if ($existing['role'] === 'admin') {
        exit("That user is already an admin.\n");
    }
    if (strtolower(ask("User exists as {$existing['role']}. Promote to admin? (yes/no): ")) !== 'yes') {
        exit("Cancelled.\n");
    }
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE users SET role = 'admin', verification_status = 'verified' WHERE user_id = ?")
        ->execute([$existing['user_id']]);
    $userId = (int) $existing['user_id'];
} else {
    $name = ask('Full name: ');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        exit("Name must be 2 to 100 characters.\n");
    }
    $password = ask('Password (hidden): ', true);
    if ($err = password_error($password)) {
        exit("$err\n");
    }
    if ($password !== ask('Repeat password: ', true)) {
        exit("Passwords do not match.\n");
    }
    $pdo->beginTransaction();
    $pdo->prepare(
        "INSERT INTO users (full_name, university_email, password_hash, role, verification_status)
         VALUES (?, ?, ?, 'admin', 'verified')"
    )->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
}

// Role changes are always audited (BR-15); there is no logged-in actor, so actor_id is NULL (system).
$pdo->prepare(
    "INSERT INTO audit_logs (actor_id, action, target_type, target_id, details)
     VALUES (NULL, 'admin_created_cli', 'user', ?, 'Created or promoted through scripts/create_admin.php')"
)->execute([$userId]);
$pdo->commit();

echo "Done. $email is now an admin (user_id $userId).\n";