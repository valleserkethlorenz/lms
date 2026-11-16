<?php
/**
 * Stored-document assets (local files + Cloudinary URLs).
 *
 * `tbl_submitted_document.stored_name` and `tbl_player.document` hold either a
 * bare file name (private storage on the instance) or the Cloudinary secure URL
 * returned by validatePlayerDocuments() when cloud storage is enabled:
 *
 *   local:    3f8a…c1.pdf                        → storage/private/documents/
 *   cloud:    https://res.cloudinary.com/<cloud>/image/upload/v…/lms/documents/…
 *
 * Both shapes share one column, so anything that deletes a stored document must
 * branch on it. Keeping that branch here means the registration endpoint and
 * the correction endpoint clean up both kinds instead of leaking cloud assets
 * (or leaving replaced files behind) when a submission is rolled back.
 *
 * Cloudinary is already supported on the read side — see
 * includes/downloadDocument.php, which proxies preview/downloads for both.
 */

require_once __DIR__ . '/documentStorage.php';
require_once __DIR__ . '/storageService.php';

if (!function_exists('storedDocumentCloudRef')) {
    /**
     * Split a Cloudinary delivery URL into the parts StorageService::delete()
     * needs (public id + resource type).
     *
     * @return array{public_id:string, resource_type:string}|null Null when the
     *         value is not a Cloudinary URL this app produced.
     */
    function storedDocumentCloudRef(string $url): ?array
    {
        $parts = parse_url(trim($url));
        if (!$parts || empty($parts['host']) || stripos((string) $parts['host'], 'res.cloudinary.com') === false) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', (string) ($parts['path'] ?? '')), static fn($part) => $part !== ''));
        if (count($segments) < 4) {
            return null;
        }

        // URL shape: /<cloud_name>/<resource_type>/upload/[v…]/<public_id>
        $uploadIndex = array_search('upload', array_map('strtolower', $segments), true);
        if ($uploadIndex === false || $uploadIndex < 1) {
            return null;
        }
        $resourceType = strtolower((string) $segments[$uploadIndex - 1]); // image | raw | video
        if (!in_array($resourceType, ['image', 'raw', 'video'], true)) {
            return null;
        }
        $segments = array_slice($segments, $uploadIndex + 1);

        // Drop the version folder (v1729…) that Cloudinary adds to every URL.
        while ($segments && preg_match('/^v\d+$/', (string) $segments[0])) {
            array_shift($segments);
        }

        $publicId = implode('/', $segments);
        if ($publicId === '') {
            return null;
        }

        // Image/video public ids are stored without the delivery extension;
        // raw assets keep their file name (including the extension).
        if ($resourceType !== 'raw') {
            $publicId = preg_replace('/\.[A-Za-z0-9]{1,5}$/', '', $publicId) ?? $publicId;
        }

        return ['public_id' => $publicId, 'resource_type' => $resourceType];
    }
}

if (!function_exists('deleteStoredDocument')) {
    /**
     * Delete one stored document, wherever it lives.
     *
     * @param string $stored Bare local file name or Cloudinary secure URL.
     */
    function deleteStoredDocument(string $stored): bool
    {
        $stored = trim($stored);
        if ($stored === '') {
            return false;
        }

        if (StorageService::isCloudinary($stored)) {
            $ref = storedDocumentCloudRef($stored);
            return $ref !== null && StorageService::delete($ref['public_id'], $ref['resource_type']);
        }

        $path = documentStoredPath($stored);
        return $path !== null && is_file($path) && @unlink($path);
    }
}
