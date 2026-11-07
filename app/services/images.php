<?php
declare(strict_types=1);

const IMAGE_MAX_BYTES  = 2097152;     // 2 MB per image
const IMAGE_MAX_PIXELS = 16000000;    // 16 megapixels (protects server memory)
const IMAGE_MAX_COUNT  = 4;
const IMAGE_TYPES      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function image_upload_dir(): string
{
    return BASE_PATH . '/public/uploads/items';
}

/** Turns $_FILES['images'] into a simple list and skips empty slots. */
function image_collect_uploads(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) {
        return [];
    }
    $files = [];
    foreach ($_FILES[$field]['name'] as $i => $name) {
        $err = $_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        $tmp = $_FILES[$field]['tmp_name'][$i] ?? '';
        if ($err === UPLOAD_ERR_NO_FILE || !is_string($name) || !is_string($tmp) || !is_int($err)) {
            continue;
        }
        $files[] = [
            'name'     => $name,
            'tmp_name' => $tmp,
            'size'     => (int) ($_FILES[$field]['size'][$i] ?? 0),
            'error'    => $err,
        ];
    }
    return $files;
}

/** Returns an error message, or null if the upload is acceptable. Nothing is stored here. */
function image_validate(array $f): ?string
{
    $label = mb_substr($f['name'] !== '' ? $f['name'] : 'An image', 0, 40);

    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return "$label is too large (maximum 2 MB per image).";
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return "$label could not be uploaded. Please try again.";
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        return "$label is not a valid upload.";
    }
    if ($f['size'] <= 0 || $f['size'] > IMAGE_MAX_BYTES) {
        return "$label is too large (maximum 2 MB per image).";
    }

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return "$label must be a JPG, PNG or WebP image.";
    }

    // The REAL type comes from the file's content, not its name or the browser's claim.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!is_string($mime) || !isset(IMAGE_TYPES[$mime])) {
        return "$label is not a valid image. Only real JPG, PNG or WebP files are accepted.";
    }
    $info = @getimagesize($f['tmp_name']);
    if ($info === false || $info['mime'] !== $mime) {
        return "$label is not a valid image. Only real JPG, PNG or WebP files are accepted.";
    }
    if ($info[0] * $info[1] > IMAGE_MAX_PIXELS) {
        return "$label has too many pixels (maximum 16 megapixels).";
    }
    return null;
}

/** Checks the count and every file. Returns the first error or null. */
function image_validate_all(array $files, int $alreadyAttached): ?string
{
    if ($alreadyAttached + count($files) > IMAGE_MAX_COUNT) {
        return 'A listing can have at most ' . IMAGE_MAX_COUNT . ' images in total (this one already has ' . $alreadyAttached . ').';
    }
    foreach ($files as $f) {
        if ($err = image_validate($f)) {
            return $err;
        }
    }
    return null;
}

/**
 * Re-encodes the image with GD and saves it under a random name. Returns the stored file name.
 * Re-encoding drops any hidden payload and metadata.
 */
function image_process(array $f, ?string $dir = null): string
{
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(IMAGE_TYPES[$mime])) {
        throw new RuntimeException('Unsupported image type.');
    }

    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
        'image/png'  => @imagecreatefrompng($f['tmp_name']),
        default      => @imagecreatefromwebp($f['tmp_name']),
    };
    if ($img === false) {
        throw new RuntimeException('One of the images could not be read. Please try a different file.');
    }

    // Phone photos store rotation in metadata, which re-encoding removes, so apply it first.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif        = @exif_read_data($f['tmp_name']);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle       = match ($orientation) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($angle !== 0) {
            $rotated = imagerotate($img, $angle, 0);
            if ($rotated !== false) {
                $img = $rotated;
            }
        }
    }

    $dir = $dir ?? image_upload_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    $name = bin2hex(random_bytes(16)) . '.' . IMAGE_TYPES[$mime];
    $path = $dir . '/' . $name;

    if ($mime === 'image/jpeg') {
        $ok = imagejpeg($img, $path, 85);
    } elseif ($mime === 'image/png') {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $ok = imagepng($img, $path, 6);
    } else {
        $ok = imagewebp($img, $path, 85);
    }
    if (!$ok) {
        @unlink($path);
        throw new RuntimeException('The image could not be saved.');
    }
    return $name;
}

/** Removes stored files again (used when the database step fails after files were written). */
function image_delete_files(array $names): void
{
    foreach ($names as $n) {
        @unlink(image_upload_dir() . '/' . basename((string) $n));
    }
}