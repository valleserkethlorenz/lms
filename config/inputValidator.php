<?php
/**
 * Input Validation Helper
 * Provides comprehensive input validation and sanitization functions.
 *
 * Usage:
 *   require_once __DIR__ . '/inputValidator.php';
 *   $validator = new InputValidator();
 *   $email = $validator->email($_POST['email'] ?? '');
 *   $name = $validator->name($_POST['name'] ?? '', 2, 100);
 */

class InputValidator
{
    /**
     * Sanitize and validate email address.
     */
    public function email(string $email): string
    {
        $email = trim($email);
        if ($email === '') throw new InvalidArgumentException('Email is required.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Invalid email address.');
        if (strlen($email) > 255) throw new InvalidArgumentException('Email is too long.');
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize and validate a name (first, last, etc.).
     */
    public function name(string $name, int $minLength = 1, int $maxLength = 100): string
    {
        $name = trim($name);
        if ($name === '') throw new InvalidArgumentException('Name is required.');
        if (strlen($name) < $minLength) throw new InvalidArgumentException("Name must be at least $minLength characters.");
        if (strlen($name) > $maxLength) throw new InvalidArgumentException("Name must not exceed $maxLength characters.");
        if (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $name)) throw new InvalidArgumentException('Name contains invalid characters.');
        return htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize and validate a phone number.
     */
    public function phone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') throw new InvalidArgumentException('Phone number is required.');
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($cleaned) < 7 || strlen($cleaned) > 20) throw new InvalidArgumentException('Phone number must be between 7 and 20 digits.');
        return $cleaned;
    }

    /**
     * Sanitize and validate an integer ID.
     */
    public function id($id, string $fieldName = 'ID'): int
    {
        if ($id === null || $id === '') throw new InvalidArgumentException("$fieldName is required.");
        $intId = filter_var($id, FILTER_VALIDATE_INT);
        if ($intId === false || $intId <= 0) throw new InvalidArgumentException("Invalid $fieldName.");
        return $intId;
    }

    /**
     * Sanitize and validate a date string (YYYY-MM-DD format).
     */
    public function date(string $date, string $fieldName = 'Date'): string
    {
        $date = trim($date);
        if ($date === '') throw new InvalidArgumentException("$fieldName is required.");
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateTime || $dateTime->format('Y-m-d') !== $date) throw new InvalidArgumentException("Invalid $fieldName format. Use YYYY-MM-DD.");
        return $date;
    }

    /**
     * Sanitize a generic text field.
     */
    public function text(string $text, int $maxLength = 255, bool $required = true): string
    {
        $text = trim($text);
        if ($required && $text === '') throw new InvalidArgumentException('This field is required.');
        if (strlen($text) > $maxLength) throw new InvalidArgumentException("Text must not exceed $maxLength characters.");
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize a textarea/long text field.
     */
    public function textarea(string $text, int $maxLength = 5000, bool $required = true): string
    {
        $text = trim($text);
        if ($required && $text === '') throw new InvalidArgumentException('This field is required.');
        if (strlen($text) > $maxLength) throw new InvalidArgumentException("Text must not exceed $maxLength characters.");
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate that a value is in a list of allowed values.
     */
    public function inList($value, array $allowed, string $fieldName = 'Value')
    {
        if (!in_array($value, $allowed, true)) throw new InvalidArgumentException("Invalid $fieldName.");
        return $value;
    }

    /**
     * Validate a boolean value.
     */
    public function bool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Validate file upload.
     */
    public function file(array $file, array $allowedMimes, int $maxSizeBytes): array
    {
        if (!isset($file['error']) || is_array($file['error'])) throw new InvalidArgumentException('Invalid file upload.');
        switch ($file['error']) {
            case UPLOAD_ERR_OK: break;
            case UPLOAD_ERR_NO_FILE: throw new InvalidArgumentException('No file was uploaded.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: throw new InvalidArgumentException('File exceeds the maximum size limit.');
            default: throw new InvalidArgumentException('Unknown upload error.');
        }
        if ($file['size'] > $maxSizeBytes) throw new InvalidArgumentException('File is too large.');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedMimes, true)) throw new InvalidArgumentException('Invalid file type.');
        return ['tmp_name' => $file['tmp_name'], 'name' => basename($file['name']), 'size' => $file['size'], 'mime' => $mime];
    }
}

/**
 * Helper function to safely get and validate POST data.
 */
function validatePost(string $key, callable $validator, $default = null)
{
    if (!isset($_POST[$key])) return $default;
    try { return $validator($_POST[$key]); } catch (InvalidArgumentException $e) { throw $e; }
}

/**
 * Helper function to safely get and validate GET data.
 */
function validateGet(string $key, callable $validator, $default = null)
{
    if (!isset($_GET[$key])) return $default;
    try { return $validator($_GET[$key]); } catch (InvalidArgumentException $e) { throw $e; }
}
