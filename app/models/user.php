<?php
declare(strict_types=1);

/** Safe user columns (no password hash). is_suspended is computed by MySQL: expired suspensions count as lifted. */
const USER_COLUMNS = "user_id, student_id, full_name, university_email, phone, role,
    verification_status, account_status, suspended_until, suspension_reason,
    (account_status = 'suspended' AND (suspended_until IS NULL OR suspended_until > NOW())) AS is_suspended";

function user_find_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT ' . USER_COLUMNS . ' FROM users WHERE user_id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Includes password_hash: only for login. */
function user_find_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT ' . USER_COLUMNS . ', password_hash FROM users WHERE university_email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function user_student_id_exists(string $studentId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM users WHERE student_id = ?');
    $stmt->execute([$studentId]);
    return (bool) $stmt->fetchColumn();
}

function user_password_hash(int $id): string
{
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $stmt->execute([$id]);
    return (string) $stmt->fetchColumn();
}

/** Always creates a STUDENT (BR-14). The role is not a parameter on purpose. */
function user_create(string $fullName, string $email, string $passwordHash, ?string $studentId, ?string $phone): int
{
    $stmt = db()->prepare(
        "INSERT INTO users (student_id, full_name, university_email, password_hash, phone, role)
         VALUES (?, ?, ?, ?, ?, 'student')"
    );
    $stmt->execute([$studentId, $fullName, $email, $passwordHash, $phone]);
    return (int) db()->lastInsertId();
}