<?php
/**
 * Document streaming helpers (shared).
 *
 * Used by includes/downloadDocument.php to serve submitted files without
 * sending the browser to Cloudinary:
 *
 *   mode=preview   → streamed inline so the in-app preview modal can show it
 *   mode=download  → streamed as an attachment with a readable filename
 *   (no mode)      → legacy behaviour: local files inline, Cloudinary redirect
 *
 * Keeping the helpers here (instead of inside the endpoint) makes the filename
 * and MIME handling unit-testable — see tests/Unit/DocumentStreamTest.php.
 */

if (!defined('DOCUMENT_STREAM_MAX_BYTES')) {
    define('DOCUMENT_STREAM_MAX_BYTES', 26214400); // 25 MB — uploads are capped far lower.
}

if (!function_exists('documentDownloadName')) {
    /**
     * Readable, header-safe file name for Content-Disposition.
     */
    function documentDownloadName(array $document): string
    {
        $name = trim((string) ($document['original_name'] ?? ''));
        if ($name === '') {
            $name = (string) ($document['stored_name'] ?? '');
        }
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($name)) ?? '';
        return $name !== '' ? $name : 'document';
    }
}

if (!function_exists('documentMimeFromName')) {
    /**
     * Resolve the Content-Type for a streamed document.
     *
     * The stored/original file name wins (Cloudinary raw uploads often answer
     * with application/octet-stream); the remote Content-Type is only used when
     * it is a type the browser can render or download safely.
     */
    function documentMimeFromName(string $name, string $fallback = 'application/octet-stream'): string
    {
        $path = (string) (parse_url($name, PHP_URL_PATH) ?: $name);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $byExtension = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif'  => 'image/gif',  'webp' => 'image/webp', 'bmp' => 'image/bmp',
            'txt'  => 'text/plain; charset=UTF-8',
        ];
        if (isset($byExtension[$extension])) {
            return $byExtension[$extension];
        }

        $allowed = [
            'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
            'text/plain', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        $remote = strtolower(trim((string) (explode(';', $fallback)[0] ?? '')));
        return in_array($remote, $allowed, true) ? $remote : 'application/octet-stream';
    }
}

if (!function_exists('fetchCloudinaryDocument')) {
    /**
     * Fetch a Cloudinary file server-side so it can be streamed by the app.
     *
     * @return array{body:string, mime:string}|null Null when the file cannot be
     *                                              fetched or exceeds the size cap.
     */
    function fetchCloudinaryDocument(string $url): ?array
    {
        if (!function_exists('curl_init')) {
            error_log('[downloadDocument] cURL is not available; cannot stream Cloudinary files.');
            return null;
        }

        $body = '';
        $handle = curl_init($url);
        if ($handle === false) {
            return null;
        }
        curl_setopt_array($handle, [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'LMS-DocumentPreview/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: application/pdf,image/*,text/plain,*/*;q=0.8'],
            // Stream into memory but refuse anything above the size cap.
            CURLOPT_WRITEFUNCTION  => static function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > DOCUMENT_STREAM_MAX_BYTES) {
                    return 0; // aborts the transfer
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $result = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $mime   = (string) (curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: '');
        $error  = curl_error($handle);
        curl_close($handle);

        if ($result === false || $status !== 200 || $body === '') {
            error_log('[downloadDocument] Cloudinary fetch failed (HTTP ' . $status . '): '
                . ($error !== '' ? $error : 'empty response'));
            return null;
        }
        return ['body' => $body, 'mime' => $mime];
    }
}

if (!function_exists('sendDocumentHeaders')) {
    /**
     * Headers shared by local and Cloudinary documents.
     */
    function sendDocumentHeaders(string $mime, int $length, bool $inline, string $downloadName): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $length);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
            . '; filename="' . addcslashes($downloadName, "\"\\") . '"');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: private, max-age=300');
    }
}
