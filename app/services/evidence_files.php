<?php
declare(strict_types=1);

function evidence_dir(): string
{
    return BASE_PATH . '/storage/evidence';       // OUTSIDE the web root
}

/** The single optional proof upload, or null. */
function proof_collect_upload(): ?array
{
    $f = $_FILES['proof'] ?? null;
    if (!is_array($f) || !is_string($f['name'] ?? null) || !is_string($f['tmp_name'] ?? null) || !is_int($f['error'] ?? null)) {
        return null;
    }
    if ($f['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return ['name' => $f['name'], 'tmp_name' => $f['tmp_name'], 'size' => (int) ($f['size'] ?? 0), 'error' => $f['error']];
}

/** Error message or null. Images use the same checks as listing images; PDFs are checked by content. */
function proof_validate(array $f): ?string
{
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return image_validate($f);
    }

    $label = mb_substr($f['name'] !== '' ? $f['name'] : 'The file', 0, 40);
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return "$label is too large (maximum 2 MB).";
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return "$label could not be uploaded. Please try again.";
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        return "$label is not a valid upload.";
    }
    if ($f['size'] <= 0 || $f['size'] > IMAGE_MAX_BYTES) {
        return "$label is too large (maximum 2 MB).";
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $head = (string) file_get_contents($f['tmp_name'], false, null, 0, 5);
    if ($mime !== 'application/pdf' || $head !== '%PDF-') {
        return "$label is not a valid PDF.";
    }
    return null;
}

/** Stores the validated file under a random name and returns that name. */
function proof_store(array $f): string
{
    $dir = evidence_dir();
    if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) === 'pdf') {
        $name = bin2hex(random_bytes(16)) . '.pdf';
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
            throw new RuntimeException('The file could not be saved.');
        }
        chmod($dir . '/' . $name, 0660);
        return $name;
    }
    return image_process($f, $dir);               // re-encoded image
}

function proof_delete(string $name): void
{
    @unlink(evidence_dir() . '/' . basename($name));
}