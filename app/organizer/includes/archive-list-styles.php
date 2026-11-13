<style>
/* Flat companion to the organizer league register. */
.organizer-archive { --archive-navy:#09173c; --archive-line:#e4e9f2; --archive-muted:#6f7a8d; }
.organizer-archive .archive-policy { display:flex; align-items:center; gap:12px; padding:16px 20px; margin-bottom:24px; border:1px solid var(--archive-line); border-radius:3px; background:#f4f6fb; color:#596780; font-size:12px; line-height:1.7; }
.organizer-archive .archive-policy > i { color:#2f6fed; font-size:18px; }
.organizer-archive .archive-policy strong { color:#34415a; font-weight:600; }
.organizer-archive .archive-policy a { margin-left:auto; white-space:nowrap; color:#17356f; font-size:11px; font-weight:600; text-decoration:none; }
.organizer-archive .activity-section { border:1px solid var(--archive-line); border-radius:4px; box-shadow:none; }
.organizer-archive .activity-header { padding:22px 24px; border-bottom:1px solid var(--archive-line); gap:16px; }
.organizer-archive .activity-badge-count { background:#f0f3f8; color:#596780; border-radius:3px; font-size:10px; letter-spacing:0; }
.organizer-archive .archive-search-input { width:280px; max-width:100%; background:#fff; color:var(--archive-navy); border:1px solid #d6deea; border-radius:3px; padding:10px 12px; font:inherit; font-size:12px; }
.organizer-archive #archiveTable { font-variant-numeric:tabular-nums; }
.organizer-archive #archiveTable thead th { background:#f4f6fb; color:#67748a; font-size:10px; font-weight:600; letter-spacing:.07em; padding:14px 16px; border-bottom:1px solid var(--archive-line); }
.organizer-archive #archiveTable thead th i { display:none; }
.organizer-archive #archiveTable tbody td { padding:19px 16px; font-size:12px; color:#34415a; border-color:#eef1f7; }
.organizer-archive #archiveTable tbody td:first-child { color:#98a1b2; font-size:11px; }
.organizer-archive #archiveTable tbody td:nth-child(2) { min-width:180px; color:var(--archive-navy); }
.organizer-archive #archiveTable strong { font-weight:600; }
.organizer-archive #archiveTable tbody td:nth-child(n+7) { white-space:nowrap; }
.organizer-archive #archiveTable tbody tr:hover { background:#f8faff; }
.organizer-archive .archive-badge-warning,
.organizer-archive .archive-badge-danger { display:inline-block; border:1px solid #ecd399; border-radius:3px; padding:5px 8px; font-size:10px; font-weight:600; white-space:nowrap; background:#fff3d9; color:#89580b; }
.organizer-archive .archive-badge-danger { background:#fdebea; color:#a12c25; border-color:#edc1bd; }
.organizer-archive .archive-restore-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; background:#e7f3ed; color:#146c43; border:1px solid #b8dfc9; border-radius:3px; padding:8px 12px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap; }
.organizer-archive .archive-restore-btn:hover { background:#146c43; border-color:#146c43; color:#fff; }
.organizer-archive .archive-empty { padding:64px 24px; text-align:center; }
.organizer-archive .archive-empty-icon { display:inline-flex; align-items:center; justify-content:center; width:56px; height:56px; border:1px solid var(--archive-line); border-radius:4px; background:#f4f6fb; color:#67748a; font-size:24px; margin-bottom:18px; }
.organizer-archive .archive-empty-title { font-size:17px; font-weight:600; color:var(--archive-navy); margin-bottom:8px; }
.organizer-archive .archive-empty-text { color:var(--archive-muted); font-size:12px; line-height:1.8; max-width:40ch; margin:0 auto 20px; }
.organizer-archive .archive-empty-link { color:#17356f; font-size:12px; font-weight:600; text-decoration:none; }
.organizer-archive .archive-policy a:hover,
.organizer-archive .archive-empty-link:hover { text-decoration:underline; }
.organizer-archive a:focus-visible,
.organizer-archive button:focus-visible,
.organizer-archive input:focus-visible { outline:2px solid #2f6fed; outline-offset:3px; }
@media(max-width:768px) {
    .organizer-archive .archive-policy { flex-wrap:wrap; align-items:flex-start; padding:16px; }
    .organizer-archive .archive-policy span { flex:1; min-width:180px; }
    .organizer-archive .archive-policy a { margin-left:30px; }
    .organizer-archive .activity-header { padding:16px; }
    .organizer-archive .archive-search-input { width:100%; }
}
@media(prefers-reduced-motion:reduce) { .organizer-archive .dash-animate { animation:none; } }
</style>
