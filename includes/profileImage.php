<?php
/**
 * Profile photo helpers shared by the organizer and committee account pages.
 *
 * The admin account screens store a photo as either a Cloudinary URL or a
 * project-relative path (e.g. "../../uploads/profiles/profile_x.jpg"). These
 * helpers keep the read, store and delete rules in one place so every role
 * handles the same value the same way.
 */

/**
 * Allowed upload MIME types mapped to the extension used on disk.
 */
function profileImageAllowedMimes(): array
{
    return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
}

/**
 * Whether the stored value points at a remote (Cloudinary) asset.
 */
function profileImageIsRemote(?string $stored): bool
{
    return (bool) preg_match('#^https?://#i', trim((string) $stored));
}

/**
 * Convert the value stored in tbl_user.profile_image into a URL that pages
 * inside app/<role>/ (two directories below the project root) can load.
 *
 * Local files must still exist on disk; stale database values return an empty
 * string so views can fall back to their placeholder instead of a broken image.
 */
function profileImageWebPath(?string $stored): string
{
    $value = trim((string) $stored);
    if ($value === '') return '';
    if (profileImageIsRemote($value)) return $value;

    // Admin pages store '../../uploads/profiles/...'; older records may hold
    // 'uploads/profiles/...'. Only paths inside uploads/ are ever exposed.
    $relative = ltrim(preg_replace('#^((\.\./)+|/)#', '', str_replace('\\', '/', $value)), '/');
    if (!str_starts_with($relative, 'uploads/')) return '';

    $projectRoot = dirname(__DIR__);
    if (!is_file($projectRoot . '/' . $relative)) return '';

    return '../../' . $relative;
}

/**
 * Human readable message for a PHP upload error code.
 */
function profileImageUploadError(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The photo is too large for the server. Please upload an image smaller than 2 MB.',
        UPLOAD_ERR_PARTIAL => 'The photo upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload folder.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not save the photo.',
        UPLOAD_ERR_EXTENSION => 'The upload was blocked by a server extension.',
        default => 'The photo could not be uploaded. Please try again.',
    };
}

/**
 * Validate and store an uploaded profile photo.
 *
 * @return string The value to persist in tbl_user.profile_image.
 * @throws RuntimeException When the file is missing, invalid, too large, or
 *                          cannot be stored.
 */
function profileImageStoreUpload(array $file, int $userId, string $projectRoot, string $folderKey = 'profiles'): string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please choose a photo to upload.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(profileImageUploadError($error));
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('The uploaded photo could not be read. Please try again.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 2 * 1024 * 1024) {
        throw new RuntimeException('The photo must be smaller than 2 MB.');
    }

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
    } elseif (function_exists('mime_content_type')) {
        $mime = (string) mime_content_type($tmp);
    }
    $mimes = profileImageAllowedMimes();
    if (!isset($mimes[$mime]) || @getimagesize($tmp) === false) {
        throw new RuntimeException('Upload a valid JPG, PNG, or GIF image.');
    }

    require_once __DIR__ . '/storageService.php';
    if (StorageService::isEnabled()) {
        $upload = StorageService::upload($tmp, $folderKey, [
            'public_id' => 'profile_' . $userId . '_' . bin2hex(random_bytes(8)),
        ]);
        return (string) $upload['secure_url'];
    }

    $directory = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/uploads/profiles';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The profile photo folder could not be created.');
    }

    $filename = 'profile_' . $userId . '_' . bin2hex(random_bytes(12)) . '.' . $mimes[$mime];
    if (!move_uploaded_file($tmp, $directory . '/' . $filename)) {
        throw new RuntimeException('The photo could not be saved. Please try again.');
    }

    return '../../uploads/profiles/' . $filename;
}

/**
 * Extract the Cloudinary public_id from a delivery URL.
 */
function profileImageCloudinaryId(string $url): string
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    if (!preg_match('#/upload/(?:v\d+/)?(.+)$#i', $path, $matches)) return '';
    return (string) preg_replace('/\.[a-z0-9]+$/i', '', $matches[1]);
}

/**
 * Delete a previously stored profile photo (local file or Cloudinary asset).
 * Failures are silent: replacing a photo must never break the account save.
 */
function profileImageDeleteStored(?string $stored, string $projectRoot): void
{
    $value = trim((string) $stored);
    if ($value === '') return;

    if (profileImageIsRemote($value)) {
        require_once __DIR__ . '/storageService.php';
        if (StorageService::isCloudinary($value)) {
            $publicId = profileImageCloudinaryId($value);
            if ($publicId !== '') StorageService::delete($publicId);
        }
        return;
    }

    $relative = ltrim(preg_replace('#^((\.\./)+|/)#', '', str_replace('\\', '/', $value)), '/');
    if (!str_starts_with($relative, 'uploads/')) return;

    $root = rtrim(str_replace('\\', '/', $projectRoot), '/');
    $uploadsRoot = realpath($root . '/uploads');
    $target = realpath($root . '/' . $relative);
    if ($uploadsRoot === false || $target === false) return;

    $uploadsRoot = rtrim(str_replace('\\', '/', $uploadsRoot), '/') . '/';
    if (str_starts_with(str_replace('\\', '/', $target), $uploadsRoot) && is_file($target)) {
        @unlink($target);
    }
}
