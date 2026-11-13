<style>
/* Photo collections keep the imagery prominent and the surrounding UI quiet. */
.organizer-gallery { --gallery-line:#e4e9f2; --gallery-navy:#09173c; }
.organizer-gallery .upload-section { border:1px solid var(--gallery-line); border-radius:4px; box-shadow:none; }
.organizer-gallery .upload-section-header { padding:22px 24px; border-color:var(--gallery-line); }
.organizer-gallery .upload-section-body { padding:16px 24px; }
.organizer-gallery .post-field-label { font-size:11px; text-transform:none; letter-spacing:0; color:#596780; margin-bottom:7px; }
.organizer-gallery .post-field-input { background:#fff; border:1px solid #d6deea; border-radius:3px; font-size:12px; padding:10px 12px; }
.organizer-gallery textarea.post-field-input { min-height:40px; }
.organizer-gallery .upload-box { padding:18px 20px; border:1px dashed #aebdd3; border-radius:3px; background:#f4f6fb; }
.organizer-gallery .upload-box:hover,
.organizer-gallery .upload-box.dragover { background:#edf3ff; border-color:#2f6fed; box-shadow:none; }
.organizer-gallery .upload-box-icon { font-size:28px; color:#59739f; }
.organizer-gallery .upload-box-text { font-size:13px; color:#34415a; }
.organizer-gallery .upload-box-hint { font-size:11px; line-height:1.8; color:#6f7a8d; }
.organizer-gallery .image-preview-container { grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); }
.organizer-gallery .image-preview .remove-btn { border-radius:3px; box-shadow:none; }
.organizer-gallery .gallery-publish-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:8px; padding-top:12px; border-top:1px solid #eef1f7; }
.organizer-gallery .publish-btn,
.organizer-gallery .clear-btn { font-size:12px; padding:8px 16px; border-radius:3px; box-shadow:none; }
.organizer-gallery .publish-btn { background:var(--gallery-navy); }
.organizer-gallery .publish-btn:hover { background:#17356f; transform:none; box-shadow:none; }
.organizer-gallery .clear-btn:hover { transform:none; box-shadow:none; }
.organizer-gallery .gallery-collection-heading { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin:24px 0 16px; }
.organizer-gallery .gallery-post { height:100%; border:1px solid var(--gallery-line); border-radius:4px; box-shadow:none; }
.organizer-gallery .gallery-post:hover { transform:none; box-shadow:none; border-color:#b4c3dc; }
.organizer-gallery .gallery-post-images { padding:8px; gap:4px; background:#f4f6fb; }
.organizer-gallery .gallery-post-images img { height:150px; border-radius:2px; }
.organizer-gallery .gallery-post-images img:hover { transform:none; opacity:.9; }
.organizer-gallery .gallery-post-info { padding:16px; }
.organizer-gallery .gallery-post-title { font-size:16px; font-weight:600; letter-spacing:-.02em; overflow-wrap:anywhere; }
.organizer-gallery .gallery-post-desc { font-size:12px; line-height:1.8; overflow-wrap:anywhere; }
.organizer-gallery .gallery-post-meta { gap:12px; flex-wrap:wrap; padding-top:14px; border-top:1px solid #eef1f7; color:#6f7a8d; }
.organizer-gallery .gallery-post-actions .btn-sm { border-radius:3px; font-size:11px; padding:6px 10px; }
.organizer-gallery .modal-content { border-radius:14px; }
.organizer-gallery .modal-header.gradient { background:var(--gallery-navy); }
.organizer-gallery .modal-title { font-size:17px; font-weight:600; }
.organizer-gallery button:focus-visible,
.organizer-gallery input:focus-visible,
.organizer-gallery textarea:focus-visible { outline:2px solid #2f6fed; outline-offset:3px; }
@media(max-width:768px) {
    .organizer-gallery .upload-section-header,
    .organizer-gallery .upload-section-body { padding:16px; }
    .organizer-gallery .gallery-publish-actions > button { flex:1; justify-content:center; }
}
@media(prefers-reduced-motion:reduce) {
    .organizer-gallery .upload-section,
    .organizer-gallery .posts-section { animation:none; }
}
</style>
