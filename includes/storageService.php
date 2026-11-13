<?php
/**
 * StorageService – Cloudinary abstraction layer
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';
use Cloudinary\Cloudinary;

class StorageService
{
    private static ?Cloudinary $cloudinary = null;
    private static array $config = [];
    private static bool $enabled = false;

    private static function init(): void
    {
        if (self::$cloudinary !== null) return;
        $configFile = dirname(__DIR__) . '/config/cloudinary.php';
        if (!is_file($configFile)) return;
        self::$config = require $configFile;
        $cn = self::$config['cloud_name'] ?? '';
        if ($cn === '' || $cn === 'YOUR_CLOUD_NAME') return;
        self::$cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $cn,
                'api_key'    => self::$config['api_key'] ?? '',
                'api_secret' => self::$config['api_secret'] ?? '',
            ],
            'url' => [
                'timeout' => 30,
            ],
        ]);
        self::$enabled = true;
    }

    public static function isEnabled(): bool { self::init(); return self::$enabled; }

    public static function folder(string $key): string
    {
        self::init();
        return self::$config['folders'][$key] ?? 'lms/misc';
    }

    public static function upload(string $tmpPath, string $folderKey, array $options = []): array
    {
        self::init();
        if (!self::$enabled) throw new RuntimeException('Cloudinary not configured.');
        $opts = array_merge([
            'folder' => self::folder($folderKey),
            'use_filename' => true, 'unique_filename' => true, 'resource_type' => 'auto',
        ], $options);
        try {
            $r = self::$cloudinary->uploadApi()->upload($tmpPath, $opts);
            return [
                'public_id' => $r['public_id'], 'secure_url' => $r['secure_url'],
                'bytes' => $r['bytes'] ?? 0, 'format' => $r['format'] ?? '',
                'resource_type' => $r['resource_type'] ?? 'image',
            ];
        } catch (\Throwable $e) {
            error_log('[StorageService] Upload: ' . $e->getMessage());
            throw new RuntimeException('Cloud upload failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function delete(string $publicId, string $resourceType = 'image'): bool
    {
        self::init();
        if (!self::$enabled) return false;
        try {
            $opts = ($resourceType !== 'image') ? ['resource_type' => $resourceType] : [];
            $r = self::$cloudinary->uploadApi()->destroy($publicId, $opts);
            return ($r['result'] ?? '') === 'ok';
        } catch (\Throwable $e) {
            error_log('[StorageService] Delete: ' . $e->getMessage());
            return false;
        }
    }

    public static function url(string $publicId, array $transformations = [], string $resourceType = 'image'): string
    {
        self::init();
        if (!self::$enabled) return '';
        try {
            $opts = ['resource_type' => $resourceType];
            if (!empty($transformations)) $opts['transformation'] = $transformations;
            return (string)self::$cloudinary->asset($publicId, $opts)->toUrl();
        } catch (\Throwable $e) {
            error_log('[StorageService] URL: ' . $e->getMessage());
            return '';
        }
    }

    public static function signedUrl(string $publicId, int $expiresIn = 600, string $resourceType = 'raw'): string
    {
        self::init();
        if (!self::$enabled) return '';
        try {
            $opts = [
                'resource_type' => $resourceType, 'type' => 'authenticated',
                'sign_url' => true,
                'auth_token' => [
                    'key' => self::$config['api_secret'],
                    'startTime' => time(), 'expiration' => time() + $expiresIn,
                ],
            ];
            return (string)self::$cloudinary->asset($publicId, $opts)->toUrl();
        } catch (\Throwable $e) {
            error_log('[StorageService] SignedURL: ' . $e->getMessage());
            return '';
        }
    }

    public static function isCloudinary(string $value): bool
    {
        return str_starts_with($value, 'https://res.cloudinary.com/')
            || str_starts_with($value, 'http://res.cloudinary.com/');
    }

    public static function resolveUrl(string $dbValue, string $localBase = '', string $fallback = ''): string
    {
        $v = trim($dbValue);
        if ($v === '') return $fallback;
        if (self::isCloudinary($v)) return $v;
        if ($localBase !== '') return rtrim($localBase, '/') . '/' . $v;
        return $fallback;
    }

    public static function resolveReadablePath(string $dbValue): ?string
    {
        $v = trim($dbValue);
        if ($v === '') return null;
        if (self::isCloudinary($v)) return $v;
        require_once __DIR__ . '/documentStorage.php';
        return documentStoredPath($v);
    }
}
