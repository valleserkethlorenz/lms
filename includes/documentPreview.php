<?php
/**
 * Document preview helpers (shared component).
 *
 * Submitted documents used to open the raw Cloudinary URL in a new browser tab.
 * These helpers let the review screens (organizer team review + representative
 * team view) show the file inside the system instead: PDFs and images render in
 * the preview modal, everything else is offered as a download because the
 * browser has no built-in viewer for it.
 *
 * The modal markup + click controller lives in includes/documentPreviewModal.php.
 */

if (!function_exists('documentPreviewKind')) {
    /**
     * Decide how a document can be shown inside the app.
     *
     * @param string $value Original file name, stored name or a full
     *                      Cloudinary URL (the extension is read from it).
     * @return string 'pdf', 'image' or 'other'
     */
    function documentPreviewKind(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return 'other';
        }

        // Cloudinary URLs may carry query strings; only the path matters here.
        $path = (string) (parse_url($value, PHP_URL_PATH) ?: $value);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            return 'pdf';
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            return 'image';
        }
        return 'other';
    }
}
