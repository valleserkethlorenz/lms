<?php
/**
 * In-app document preview modal (shared component).
 *
 * Prints the modal plus the small controller that turns any element carrying
 * [data-doc-preview] into an in-system preview — the browser never navigates to
 * res.cloudinary.com. Files are streamed through includes/downloadDocument.php
 * (mode=preview), so the existing authorization check still applies.
 *
 * Usage (once per page, AFTER the footer include so Bootstrap JS is loaded):
 *
 *   <?php include __DIR__ . '/../../includes/documentPreviewModal.php'; ?>
 *
 * Trigger markup:
 *
 *   <a href="../../includes/downloadDocument.php?source=typed&id=12&mode=preview"
 *      data-doc-preview
 *      data-doc-kind="<?= documentPreviewKind($originalName) ?>"
 *      data-doc-title="Birth Certificate"
 *      data-doc-subtitle="birth_certificate.pdf">View file</a>
 */
require_once __DIR__ . '/documentPreview.php';

if (defined('LMS_DOC_PREVIEW_MODAL_RENDERED')) {
    return;
}
define('LMS_DOC_PREVIEW_MODAL_RENDERED', true);
?>
<style>
    /* ---------- In-app document preview ----------------------------------- */
    .lms-doc-preview .modal-content { border:0; border-radius:14px; overflow:hidden; box-shadow:0 24px 60px rgba(9,23,60,.28); }
    .lms-doc-preview .modal-header { display:flex; align-items:center; gap:14px; padding:16px 22px; background:#09173c; border:0; color:#fff; }
    .lms-doc-preview-heading { display:flex; align-items:center; gap:12px; min-width:0; }
    .lms-doc-preview-icon { display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; flex:0 0 auto; border-radius:11px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.16); font-size:1.05rem; color:#fff; }
    .lms-doc-preview .modal-title { margin:0; font-size:15px; font-weight:600; line-height:1.3; color:#fff; }
    .lms-doc-preview-sub { margin:2px 0 0; font-size:11.5px; line-height:1.4; color:rgba(255,255,255,.62); word-break:break-word; }
    .lms-doc-preview .btn-close { margin-left:auto; filter:invert(1) grayscale(1); opacity:.75; }
    .lms-doc-preview .btn-close:hover { opacity:1; }
    .lms-doc-preview .modal-body { position:relative; padding:0; min-height:340px; background:#eef2f7; }
    .lms-doc-preview-frame { display:block; width:100%; height:min(72vh,720px); border:0; background:#525659; }
    .lms-doc-preview-image { display:block; max-width:100%; max-height:72vh; margin:0 auto; padding:14px; object-fit:contain; }
    .lms-doc-preview-loading { display:flex; align-items:center; justify-content:center; gap:10px; padding:64px 20px; font-size:12.5px; font-weight:500; color:#5b6b85; }
    .lms-doc-preview-fallback { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; padding:64px 24px; text-align:center; color:#40506b; }
    .lms-doc-preview-fallback i { font-size:2rem; color:#9aa6ba; }
    .lms-doc-preview-fallback strong { font-size:13.5px; color:#09173c; }
    .lms-doc-preview-fallback p { margin:0; font-size:12px; color:#6b7280; }
    .lms-doc-preview .modal-footer { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:12px 20px; background:#fff; border-top:1px solid #e8edf4; }
    .lms-doc-preview-download { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border:1px solid #d7e0ec; border-radius:8px; background:#fff; color:#09173c; font-size:12px; font-weight:600; text-decoration:none !important; transition:background .15s ease, border-color .15s ease; }
    .lms-doc-preview-download:hover { background:#f3f7fd; border-color:#b9cbe4; color:#09173c; }
    .lms-doc-preview-close { padding:8px 16px; border:1px solid #09173c; border-radius:8px; background:#09173c; color:#fff; font-size:12px; font-weight:600; cursor:pointer; transition:background .15s ease; }
    .lms-doc-preview-close:hover { background:#142a5c; }
    @media (max-width: 575.98px) {
        .lms-doc-preview-frame { height:64vh; }
        .lms-doc-preview .modal-body { min-height:260px; }
    }
</style>

<div class="modal fade lms-doc-preview" id="lmsDocPreviewModal" tabindex="-1" aria-labelledby="lmsDocPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="lms-doc-preview-heading">
                    <span class="lms-doc-preview-icon" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></span>
                    <div>
                        <h5 class="modal-title" id="lmsDocPreviewTitle">Document</h5>
                        <p class="lms-doc-preview-sub" id="lmsDocPreviewSubtitle"></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close preview"></button>
            </div>
            <div class="modal-body">
                <div class="lms-doc-preview-loading" id="lmsDocPreviewLoading">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    <span>Loading preview…</span>
                </div>
                <iframe class="lms-doc-preview-frame" id="lmsDocPreviewFrame" title="Document preview" hidden></iframe>
                <img class="lms-doc-preview-image" id="lmsDocPreviewImage" alt="Document preview" hidden>
                <div class="lms-doc-preview-fallback" id="lmsDocPreviewFallback" hidden>
                    <i class="bi bi-file-earmark-lock" aria-hidden="true"></i>
                    <strong>This file type can’t be previewed here.</strong>
                    <p>Download the file to open it with the right application.</p>
                </div>
            </div>
            <div class="modal-footer">
                <a class="lms-doc-preview-download" id="lmsDocPreviewDownload" href="#"><i class="bi bi-download" aria-hidden="true"></i> Download file</a>
                <button type="button" class="lms-doc-preview-close" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    /* Opens [data-doc-preview] links inside the system instead of a new tab. */
    (function () {
        'use strict';

        var modalEl = document.getElementById('lmsDocPreviewModal');
        if (!modalEl || !window.bootstrap) return;

        var modal = new bootstrap.Modal(modalEl);
        var titleEl = document.getElementById('lmsDocPreviewTitle');
        var subtitleEl = document.getElementById('lmsDocPreviewSubtitle');
        var frameEl = document.getElementById('lmsDocPreviewFrame');
        var imageEl = document.getElementById('lmsDocPreviewImage');
        var loadingEl = document.getElementById('lmsDocPreviewLoading');
        var fallbackEl = document.getElementById('lmsDocPreviewFallback');
        var downloadEl = document.getElementById('lmsDocPreviewDownload');

        function withMode(href, mode) {
            try {
                var url = new URL(href, window.location.href);
                url.searchParams.set('mode', mode);
                return url.href;
            } catch (error) {
                return href;
            }
        }

        function showFallback() {
            loadingEl.hidden = true;
            frameEl.hidden = true;
            imageEl.hidden = true;
            fallbackEl.hidden = false;
        }

        function resetPreview() {
            frameEl.onload = null;
            imageEl.onload = null;
            imageEl.onerror = null;
            frameEl.hidden = true;
            imageEl.hidden = true;
            fallbackEl.hidden = true;
            loadingEl.hidden = false;
            frameEl.removeAttribute('src');
            imageEl.removeAttribute('src');
        }

        function openPreview(trigger) {
            var href = trigger.getAttribute('href') || '';
            if (!href) return;

            var kind = (trigger.getAttribute('data-doc-kind') || 'other').toLowerCase();

            titleEl.textContent = trigger.getAttribute('data-doc-title') || 'Document';
            subtitleEl.textContent = trigger.getAttribute('data-doc-subtitle') || '';
            downloadEl.href = withMode(href, 'download');

            resetPreview();

            if (kind === 'image') {
                imageEl.onload = function () { loadingEl.hidden = true; };
                imageEl.onerror = showFallback;
                imageEl.src = withMode(href, 'preview');
                imageEl.hidden = false;
            } else if (kind === 'pdf') {
                frameEl.onload = function () {
                    if (frameEl.getAttribute('src')) loadingEl.hidden = true;
                };
                frameEl.src = withMode(href, 'preview');
                frameEl.hidden = false;
            } else {
                showFallback();
            }

            modal.show();
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target && event.target.closest ? event.target.closest('[data-doc-preview]') : null;
            if (!trigger) return;
            // Modified clicks (new tab / new window / save link) stay with the browser.
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            openPreview(trigger);
        });

        modalEl.addEventListener('hidden.bs.modal', resetPreview);
    })();
</script>
