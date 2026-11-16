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

    /* ---------- Zoom / pan ------------------------------------------------- *
     * Reviewers could not enlarge a document: the image was always fitted to
     * the modal and the PDF lived in an iframe whose zoom only exists in some
     * desktop viewers. The stage now owns wheel / pinch / drag zoom for both
     * kinds, and the toolbar works on every device.                          */
    .lms-doc-preview [hidden] { display:none !important; }
    .lms-doc-preview-stage { position:relative; display:flex; align-items:center; justify-content:center; overflow:hidden; min-height:340px; }
    .lms-doc-preview-stage.is-zoomable { cursor:grab; touch-action:none; -webkit-user-select:none; user-select:none; }
    .lms-doc-preview-stage.is-panning { cursor:grabbing; }
    .lms-doc-preview-canvas { display:flex; align-items:center; justify-content:center; width:100%; min-height:340px; }
    .lms-doc-preview-frame,
    .lms-doc-preview-image { transform-origin:center center; will-change:transform; }
    .lms-doc-preview-shield { position:absolute; inset:0; z-index:2; cursor:grab; }
    .lms-doc-preview-stage.is-panning .lms-doc-preview-shield { cursor:grabbing; }
    .lms-doc-preview-toolbar { position:absolute; bottom:12px; right:14px; z-index:4; display:flex; align-items:center; gap:2px; padding:5px 7px; border:1px solid rgba(255,255,255,.16); border-radius:11px; background:rgba(9,23,60,.88); color:#fff; box-shadow:0 10px 24px rgba(9,23,60,.3); }
    .lms-doc-zoom-btn { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; padding:0; border:0; border-radius:8px; background:transparent; color:#fff; font-size:1rem; line-height:1; cursor:pointer; transition:background .15s ease, opacity .15s ease; }
    .lms-doc-zoom-btn:hover { background:rgba(255,255,255,.16); }
    .lms-doc-zoom-btn:focus-visible { outline:2px solid #93b8ff; outline-offset:1px; }
    .lms-doc-zoom-btn:disabled { opacity:.35; cursor:default; background:transparent; }
    .lms-doc-preview-zoom { min-width:46px; text-align:center; color:#fff; font-size:11.5px; font-weight:700; font-variant-numeric:tabular-nums; }
    .lms-doc-preview-hint { display:none; margin:0 6px 0 4px; color:rgba(255,255,255,.66); font-size:10.5px; }
    @media (min-width: 768px) { .lms-doc-preview-hint { display:inline; } }
    @media (max-width: 575.98px) {
        .lms-doc-preview-frame { height:64vh; }
        .lms-doc-preview .modal-body { min-height:260px; }
        .lms-doc-preview-stage,
        .lms-doc-preview-canvas { min-height:260px; }
        .lms-doc-preview-toolbar { bottom:10px; right:50%; transform:translateX(50%); padding:4px 5px; }
        .lms-doc-zoom-btn { width:38px; height:38px; }
        .lms-doc-preview-zoom { min-width:42px; }
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
                <div class="lms-doc-preview-toolbar" id="lmsDocPreviewToolbar" role="toolbar" aria-label="Document zoom" hidden>
                    <button type="button" class="lms-doc-zoom-btn" data-doc-zoom="out" aria-label="Zoom out" title="Zoom out (−)">
                        <i class="bi bi-zoom-out" aria-hidden="true"></i>
                    </button>
                    <span class="lms-doc-preview-zoom" id="lmsDocPreviewZoom" aria-live="polite">100%</span>
                    <button type="button" class="lms-doc-zoom-btn" data-doc-zoom="in" aria-label="Zoom in" title="Zoom in (+)">
                        <i class="bi bi-zoom-in" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="lms-doc-zoom-btn" data-doc-zoom="reset" aria-label="Reset zoom" title="Reset zoom (0)">
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                    </button>
                    <span class="lms-doc-preview-hint">Scroll / pinch to zoom · drag to pan</span>
                </div>

                <div class="lms-doc-preview-stage" id="lmsDocPreviewStage">
                    <div class="lms-doc-preview-canvas">
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
                    <div class="lms-doc-preview-shield" id="lmsDocPreviewShield" aria-hidden="true" hidden></div>
                </div>
            </div>
            <div class="modal-footer">
                <a class="lms-doc-preview-download" id="lmsDocPreviewDownload" href="#"><i class="bi bi-download" aria-hidden="true"></i> Download file</a>
                <a class="lms-doc-preview-download" id="lmsDocPreviewOpen" href="#" target="_blank" rel="noopener" hidden><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Open in new tab</a>
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
        var openEl = document.getElementById('lmsDocPreviewOpen');

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
            // Any zoom applied to the previous document must not leak into the
            // next one (the zoom controller re-applies it on demand).
            imageEl.style.transform = '';
            frameEl.style.transform = '';
        }

        function openPreview(trigger) {
            var href = trigger.getAttribute('href') || '';
            if (!href) return;

            var kind = (trigger.getAttribute('data-doc-kind') || 'other').toLowerCase();

            titleEl.textContent = trigger.getAttribute('data-doc-title') || 'Document';
            subtitleEl.textContent = trigger.getAttribute('data-doc-subtitle') || '';
            downloadEl.href = withMode(href, 'download');

            // The zoom controller (second script block) reads the kind from here.
            modalEl.dataset.docKind = kind;
            if (openEl) {
                openEl.href = withMode(href, 'preview');
                // Images zoom inside the modal; the browser's own viewer is only
                // useful for PDFs (pinch-zoom on phones) and unknown formats.
                openEl.hidden = kind === 'image';
            }

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

<script>
    /**
     * Zoom + pan for the in-app document preview.
     *
     * Images and PDFs are both scaled with a CSS transform, anchored at the
     * point under the cursor / fingers:
     *   desktop  – toolbar buttons, mouse wheel, double-click, drag to pan
     *   mobile   – toolbar buttons, two-finger pinch, double-tap, drag to pan
     *   keyboard – + / − / 0 while the modal is open
     *
     * A PDF keeps its own viewer (scroll + built-in zoom) at 100%. Once it is
     * scaled, an invisible shield over the iframe takes the pointer so drag and
     * pinch reach this controller instead of the embedded viewer.
     */
    (function () {
        'use strict';

        var modalEl = document.getElementById('lmsDocPreviewModal');
        var stageEl = document.getElementById('lmsDocPreviewStage');
        var toolbarEl = document.getElementById('lmsDocPreviewToolbar');
        var labelEl = document.getElementById('lmsDocPreviewZoom');
        var shieldEl = document.getElementById('lmsDocPreviewShield');
        var imageEl = document.getElementById('lmsDocPreviewImage');
        var frameEl = document.getElementById('lmsDocPreviewFrame');
        if (!modalEl || !stageEl || !toolbarEl) return;

        var MIN_SCALE = 1;
        var MAX_SCALE = 5;
        var STEP = 0.25;
        var DOUBLE_TAP_MS = 320;

        var kind = 'other';
        var scale = 1;
        var tx = 0;
        var ty = 0;
        var pointers = new Map();
        var drag = null;
        var pinch = null;
        var pinchUsed = false;
        var lastTapAt = 0;

        function activeEl() {
            if (kind === 'image') return imageEl;
            if (kind === 'pdf') return frameEl;
            return null;
        }

        function isToolbarTarget(target) {
            return !!(target && target.closest && target.closest('.lms-doc-preview-toolbar'));
        }

        function zoomButton(action) {
            return toolbarEl.querySelector('[data-doc-zoom="' + action + '"]');
        }

        function clampPan() {
            var el = activeEl();
            if (!el) return;
            var rect = stageEl.getBoundingClientRect();
            var maxX = Math.max(0, (el.offsetWidth * scale - rect.width) / 2);
            var maxY = Math.max(0, (el.offsetHeight * scale - rect.height) / 2);
            tx = Math.max(-maxX, Math.min(maxX, tx));
            ty = Math.max(-maxY, Math.min(maxY, ty));
        }



        function applyTransform() {
            var el = activeEl();
            if (el) {
                el.style.transform = (scale === 1 && tx === 0 && ty === 0)
                    ? ''
                    : 'translate(' + tx.toFixed(2) + 'px,' + ty.toFixed(2) + 'px) scale(' + scale.toFixed(3) + ')';
            }
            if (labelEl) labelEl.textContent = Math.round(scale * 100) + '%';

            var outBtn = zoomButton('out');
            var inBtn = zoomButton('in');
            var resetBtn = zoomButton('reset');
            var untouched = scale <= MIN_SCALE + 0.001 && Math.abs(tx) < 1 && Math.abs(ty) < 1;
            if (outBtn) outBtn.disabled = scale <= MIN_SCALE + 0.001;
            if (inBtn) inBtn.disabled = scale >= MAX_SCALE - 0.001;
            if (resetBtn) resetBtn.disabled = untouched;

            // A scaled PDF iframe swallows pointer events, so a transparent
            // shield covers it and the drag/pinch reaches this controller.
            // At 100% the PDF keeps its own toolbar, links and scrolling.
            if (shieldEl) shieldEl.hidden = !(kind === 'pdf' && !untouched);
        }

        /** Zoom around a viewport point (the cursor or the pinch midpoint). */
        function setScale(next, anchorClientX, anchorClientY) {
            var el = activeEl();
            if (!el) return;
            next = Math.max(MIN_SCALE, Math.min(MAX_SCALE, next));

            var rect = stageEl.getBoundingClientRect();
            // Coordinates relative to the stage centre (transform-origin).
            var px = (typeof anchorClientX === 'number' ? anchorClientX - rect.left : rect.width / 2) - rect.width / 2;
            var py = (typeof anchorClientY === 'number' ? anchorClientY - rect.top : rect.height / 2) - rect.height / 2;

            if (Math.abs(next - scale) > 0.0001) {
                // Keep the anchored point where it is while the scale changes.
                tx = px - (next / scale) * (px - tx);
                ty = py - (next / scale) * (py - ty);
                scale = next;
            }
            clampPan();
            applyTransform();
        }

        function resetZoom() {
            pointers.clear();
            drag = null;
            pinch = null;
            pinchUsed = false;
            scale = 1;
            tx = 0;
            ty = 0;
            if (imageEl) imageEl.style.transform = '';
            if (frameEl) frameEl.style.transform = '';
            stageEl.classList.remove('is-panning');
            applyTransform();
        }

        function pinchState() {
            var points = Array.from(pointers.values());
            if (points.length < 2) return null;
            var dx = points[0].x - points[1].x;
            var dy = points[0].y - points[1].y;
            return {
                distance: Math.sqrt(dx * dx + dy * dy) || 0,
                x: (points[0].x + points[1].x) / 2,
                y: (points[0].y + points[1].y) / 2
            };
        }

        /* ---------- Gestures ---------------------------------------------- */
        stageEl.addEventListener('pointerdown', function (event) {
            if (!activeEl() || isToolbarTarget(event.target)) return;
            if (event.pointerType === 'mouse' && event.button !== 0) return;

            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

            if (pointers.size === 1) {
                pinchUsed = false;
                drag = { x: event.clientX, y: event.clientY, tx: tx, ty: ty };
                pinch = null;
            } else {
                pinchUsed = true;
                drag = null;
                var pinchNow = pinchState();
                pinch = pinchNow ? { scale: scale, distance: pinchNow.distance } : null;
            }

            stageEl.classList.add('is-panning');
            try { stageEl.setPointerCapture(event.pointerId); } catch (error) {}
            if (scale > 1.001 || pointers.size > 1) event.preventDefault();
        }, { passive: false });

        stageEl.addEventListener('pointermove', function (event) {
            if (!pointers.has(event.pointerId)) return;
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

            if (pointers.size === 2 && pinch && pinch.distance > 0) {
                var state = pinchState();
                if (state) setScale(pinch.scale * (state.distance / pinch.distance), state.x, state.y);
                event.preventDefault();
                return;
            }

            if (drag && scale > 1.001) {
                tx = drag.tx + (event.clientX - drag.x);
                ty = drag.ty + (event.clientY - drag.y);
                clampPan();
                applyTransform();
                event.preventDefault();
            }
        }, { passive: false });

        function endPointer(event) {
            if (!pointers.has(event.pointerId)) return;
            pointers.delete(event.pointerId);
            if (pointers.size < 2) pinch = null;

            if (pointers.size > 0) return;
            drag = null;
            stageEl.classList.remove('is-panning');

            if (event.pointerType === 'mouse' || pinchUsed) {
                lastTapAt = 0;
                return;
            }

            var now = Date.now();
            if (now - lastTapAt < DOUBLE_TAP_MS) {
                setScale(scale > 1.001 ? MIN_SCALE : 2, event.clientX, event.clientY);
                lastTapAt = 0;
                return;
            }
            lastTapAt = now;
        }
        stageEl.addEventListener('pointerup', endPointer);
        stageEl.addEventListener('pointercancel', endPointer);


        /* ---------- Wheel + double click ---------------------------------- */
        stageEl.addEventListener('wheel', function (event) {
            // An iframe consumes its own wheel events, so the PDF viewer keeps
            // them (its own zoom) until the shield is shown.
            if (!activeEl() || isToolbarTarget(event.target) || event.target === frameEl) return;
            event.preventDefault();
            setScale(scale * (event.deltaY < 0 ? 1.12 : 1 / 1.12), event.clientX, event.clientY);
        }, { passive: false });

        stageEl.addEventListener('dblclick', function (event) {
            if (!activeEl() || isToolbarTarget(event.target) || event.target === frameEl) return;
            // Touch double-taps are handled in endPointer().
            if (Date.now() - lastTapAt < 500) return;
            setScale(scale > 1.001 ? MIN_SCALE : 2, event.clientX, event.clientY);
        });

        /* ---------- Toolbar + keyboard ------------------------------------ */
        toolbarEl.addEventListener('click', function (event) {
            var button = event.target.closest('[data-doc-zoom]');
            if (!button || button.disabled) return;
            var action = button.getAttribute('data-doc-zoom');
            if (action === 'in') setScale(scale + STEP);
            else if (action === 'out') setScale(scale - STEP);
            else resetZoom();
        });

        document.addEventListener('keydown', function (event) {
            if (!modalEl.classList.contains('show') || !activeEl()) return;
            var tag = (event.target && event.target.tagName) || '';
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            if (event.key === '+' || event.key === '=') { event.preventDefault(); setScale(scale + STEP); }
            else if (event.key === '-' || event.key === '_') { event.preventDefault(); setScale(scale - STEP); }
            else if (event.key === '0') { event.preventDefault(); resetZoom(); }
        });

        /* ---------- Modal lifecycle --------------------------------------- */
        modalEl.addEventListener('show.bs.modal', function () {
            var requested = modalEl.dataset.docKind;
            kind = (requested === 'image' || requested === 'pdf') ? requested : 'other';
            resetZoom();
            toolbarEl.hidden = kind === 'other';
            stageEl.classList.toggle('is-zoomable', kind !== 'other');
            if (shieldEl) shieldEl.hidden = true;
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            kind = 'other';
            resetZoom();
            toolbarEl.hidden = true;
            stageEl.classList.remove('is-zoomable');
            if (shieldEl) shieldEl.hidden = true;
        });

        // Natural size is only known after decode; re-clamp once it arrives.
        if (imageEl) imageEl.addEventListener('load', function () { clampPan(); applyTransform(); });
    })();
</script>

