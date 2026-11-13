<?php
/**
 * Committee matches design layer.
 *
 * A bespoke "match operations" workspace for app/committee/matches.php and the
 * dedicated match workspace (app/committee/manage_match.php).
 * It borrows the editorial language of the committee dashboard
 * (includes/dashboard-home-styles.php): navy surfaces, uppercase micro-labels,
 * joined panels, hairline borders and tabular numerals — rendered entirely in
 * flat, solid colours. No gradients are used anywhere in this file.
 *
 * Every rule is scoped under `.committee-matches` / the `.mt-` prefix so the
 * shared SB-Admin shell and the other committee pages stay untouched.
 *
 * Usage:  <?php include 'includes/matches-page-styles.php'; ?>
 */
?>
<style>
/* ---------- Tokens ------------------------------------------------------- */
.committee-matches {
    --mt-navy:#09173c; --mt-navy-2:#17356f; --mt-blue:#2f6fed;
    --mt-ink:#0d1526; --mt-muted:#6f7a8d; --mt-faint:#98a1b2;
    --mt-line:#e4e9f2; --mt-line-soft:#eef1f7; --mt-soft:#f4f6fb;
    --mt-green:#137a4e; --mt-amber:#b26a12; --mt-red:#b91c1c;
    --mt-radius:14px;
    color:var(--mt-ink);
    font-variant-numeric:tabular-nums;
}
.committee-matches a { text-decoration:none !important; }
.committee-matches a:focus-visible,
.committee-matches button:focus-visible,
.committee-matches input:focus-visible,
.committee-matches select:focus-visible { outline:2px solid var(--mt-blue); outline-offset:2px; }

/* ---------- Page shell --------------------------------------------------- */
.committee-matches .mt-page { min-width:0; padding:0 0 26px; }
.committee-matches [data-lms-toast-source]:empty { display:none; }
.committee-matches .summary-strip { margin-bottom:18px; }

/* ---------- Breadcrumb --------------------------------------------------- */
.mt-crumbs {
    display:flex; align-items:center; flex-wrap:wrap; gap:8px;
    margin:0 0 14px; font-size:12.5px; color:#75809a;
}
.mt-crumbs a {
    display:inline-flex; align-items:center; gap:6px;
    padding:4px 10px 4px 8px; border-radius:8px;
    color:var(--mt-navy); font-weight:600;
    transition:background .15s ease, color .15s ease;
}
.mt-crumbs a:hover { background:#e8edf7; color:var(--mt-blue) !important; }
.mt-crumbs a i { font-size:12px; }
.mt-crumbs .mt-crumb-sep { font-size:12px; color:#94a3b8; }
.mt-crumbs .mt-crumb-current {
    max-width:52ch; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    color:var(--mt-navy); font-weight:600;
}

/* ---------- Hero masthead ------------------------------------------------ */
.mt-hero {
    position:relative; isolation:isolate; overflow:hidden;
    display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between;
    gap:24px; padding:30px 32px; margin-bottom:16px;
    border-radius:var(--mt-radius); background:var(--mt-navy); color:#fff;
    box-shadow:0 22px 44px -34px rgba(7,17,41,.85);
}
.mt-hero::before {
    content:""; position:absolute; z-index:-1; inset:0 0 auto; height:3px;
    background:var(--mt-blue);
}
.mt-hero::after {
    content:""; position:absolute; z-index:-1;
    top:-26px; bottom:-26px; left:57%; width:1px;
    background:rgba(255,255,255,.07);
}
.mt-hero-ring {
    position:absolute; z-index:-1; border-radius:50%; pointer-events:none;
    border:1px solid rgba(255,255,255,.09);
}
.mt-hero-ring-a { top:-180px; right:-110px; width:430px; height:430px; }
.mt-hero-ring-b { bottom:-250px; right:90px; width:370px; height:370px; border-color:rgba(255,255,255,.05); }
.mt-hero-copy { position:relative; z-index:1; flex:1 1 340px; min-width:0; max-width:56ch; }
.mt-kicker {
    display:inline-flex; align-items:center; gap:9px;
    font-size:9.5px; font-weight:600; letter-spacing:.2em; text-transform:uppercase;
    color:rgba(255,255,255,.55);
}
.mt-kicker::before { content:""; width:20px; height:1px; background:rgba(255,255,255,.42); }
.mt-hero-title {
    margin:12px 0 6px; color:#fff; line-height:1.15;
    font-size:clamp(1.55rem, 2.2vw, 2rem); font-weight:600; letter-spacing:-.02em;
}
.mt-hero-sub { margin:0; max-width:52ch; font-size:13px; line-height:1.6; color:rgba(255,255,255,.66); }
.mt-hero-side {
    position:relative; z-index:1; display:flex; flex-direction:column; gap:10px;
    flex:0 1 300px; min-width:250px;
}
.mt-hero-next {
    padding:14px 16px 15px; border-radius:12px;
    border:1px solid rgba(255,255,255,.14); background:rgba(255,255,255,.06);
}
.mt-hero-next-label {
    display:block; font-size:9px; font-weight:600; letter-spacing:.18em;
    text-transform:uppercase; color:rgba(255,255,255,.52);
}
.mt-hero-next-main { display:flex; flex-wrap:wrap; align-items:baseline; gap:4px 9px; margin-top:7px; }
.mt-hero-next-date { font-size:1.15rem; font-weight:600; line-height:1; letter-spacing:-.01em; color:#fff; }
.mt-hero-next-time { font-size:11px; font-weight:500; color:rgba(255,255,255,.6); }
.mt-hero-next-fixture {
    display:block; margin-top:6px; overflow:hidden;
    text-overflow:ellipsis; white-space:nowrap;
    font-size:12.5px; font-weight:500; color:rgba(255,255,255,.86);
}
.mt-hero-next-fixture em {
    font-style:normal; padding:0 2px; font-size:9px; font-weight:600;
    letter-spacing:.12em; text-transform:uppercase; color:rgba(255,255,255,.45);
}
.mt-hero-next-meta {
    display:block; margin-top:4px; overflow:hidden;
    text-overflow:ellipsis; white-space:nowrap;
    font-size:10.5px; color:rgba(255,255,255,.5);
}
.mt-hero-next-empty { display:block; margin-top:7px; font-size:12.5px; color:rgba(255,255,255,.8); }
.mt-hero-cta {
    display:inline-flex; align-items:center; justify-content:space-between; gap:10px;
    padding:10px 14px; border-radius:10px;
    border:1px solid rgba(255,255,255,.16); color:#fff;
    font-size:11px; font-weight:600; letter-spacing:.03em;
    transition:background .18s ease, border-color .18s ease, color .18s ease;
}
.mt-hero-cta:hover { background:#fff; border-color:#fff; color:var(--mt-navy) !important; }
.mt-hero-cta i { font-size:14px; }

/* ---------- Section heading --------------------------------------------- */
.mt-section-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:4px 2px 10px; }
.mt-section-title {
    display:inline-flex; align-items:center; gap:9px;
    font-size:10.5px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:#64708a;
}
.mt-section-title::before { content:""; width:3px; height:12px; border-radius:2px; background:var(--mt-blue); }
.mt-section-note {
    display:inline-flex; align-items:center; gap:6px;
    font-size:9.5px; font-weight:600; letter-spacing:.13em; text-transform:uppercase; color:#8b95a8;
}
.mt-section-note.is-live { color:var(--mt-red); }
.mt-live-dot {
    width:6px; height:6px; border-radius:50%; background:#dc2626;
    box-shadow:0 0 0 3px rgba(220,38,38,.14);
    animation:mtPulse 1.8s ease-in-out infinite;
}
@keyframes mtPulse { 0%,100% { opacity:1; } 50% { opacity:.35; } }

/* ---------- Eyebrow / headings ------------------------------------------ */
.mt-eyebrow {
    display:block; font-size:9.5px; font-weight:600; letter-spacing:.16em;
    text-transform:uppercase; color:#8b95a8;
}
.mt-eyebrow-sep { padding:0 4px; color:#c2cad9; }

/* ---------- Match control room ------------------------------------------ */
.mt-room {
    overflow:hidden; margin-bottom:20px; background:#fff;
    border:1px solid var(--mt-line); border-radius:var(--mt-radius);
    box-shadow:0 2px 4px rgba(13,21,38,.03), 0 26px 50px -44px rgba(13,21,38,.5);
}
.mt-room-head {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:9px; padding:26px 24px 22px; text-align:center;
    background:#fff; border-bottom:1px solid var(--mt-line-soft);
}
.mt-room-league {
    font-size:9px; font-weight:600; letter-spacing:.14em;
    text-transform:uppercase; color:#8b95a8;
}
.mt-room-faceoff {
    position:relative; display:grid;
    grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);
    align-items:center; gap:20px;
    width:100%; max-width:760px; margin:0 auto;
    padding:18px 26px; border:1px solid var(--mt-line);
    border-radius:16px; background:#f8fafc;
}
.mt-room-faceoff::before {
    content:""; position:absolute; left:26px; right:26px; top:50%;
    height:1px; background:#dfe5ef; z-index:0;
}
.mt-versus-team {
    position:relative; z-index:1;
    display:flex; flex-direction:column; align-items:center; gap:9px;
    min-width:0; padding:0 18px; background:#f8fafc;
}
.mt-room-team-name {
    min-width:0; max-width:210px; text-align:center;
    overflow-wrap:anywhere; line-height:1.3;
    font-size:1.05rem; font-weight:600; letter-spacing:-.012em; color:var(--mt-navy);
}
.mt-crest {
    display:grid; place-items:center; width:62px; height:62px; flex:0 0 auto;
    border-radius:50%; background:#e4ecfb; color:#17356f;
    font-size:13px; font-weight:700; letter-spacing:.03em;
    box-shadow:0 0 0 5px #f8fafc, 0 0 0 6px #c9d9f2;
}
.mt-versus-team.is-b .mt-crest { background:#e0f2e8; color:#137a4e; box-shadow:0 0 0 5px #f8fafc, 0 0 0 6px #c4e3d2; }
.mt-room-vs {
    position:relative; z-index:1;
    display:grid; place-items:center; width:38px; height:38px; flex:0 0 auto;
    border-radius:50%; background:#fff; border:1px solid #dfe5ef;
    color:#64708a; font-size:8.5px; font-weight:700;
    letter-spacing:.06em; text-transform:uppercase;
}
.mt-room-meta {
    display:flex; flex-wrap:wrap; align-items:center; justify-content:center;
    gap:6px 14px;
}
.mt-room-meta-item { display:inline-flex; align-items:center; gap:5px; min-width:0; font-size:10.5px; color:#7c8698; }
.mt-room-meta-item i { flex:0 0 auto; font-size:11px; color:#a7b1c2; }
.mt-room-meta-item span { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.mt-ghost-btn {
    display:inline-flex; align-items:center; gap:7px; padding:8px 14px;
    border:1px solid var(--mt-line); border-radius:9px; background:#fff;
    font-size:11.5px; font-weight:600; color:#4b5563; white-space:nowrap;
    transition:background .16s ease, border-color .16s ease, color .16s ease;
}
.mt-ghost-btn:hover { background:var(--mt-navy); border-color:var(--mt-navy); color:#fff !important; }
.mt-room-grid { display:grid; grid-template-columns:minmax(0,44fr) minmax(0,56fr); align-items:stretch; }
.mt-panel { display:flex; flex-direction:column; min-width:0; padding:22px 24px 24px; }
.mt-panel + .mt-panel { border-left:1px solid var(--mt-line-soft); }
.mt-panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
.mt-panel-title { margin:0; font-size:.98rem; font-weight:600; letter-spacing:-.01em; color:var(--mt-navy); }

/* ---------- Status pills ------------------------------------------------- */
.mt-status {
    display:inline-flex; align-items:center; gap:6px; padding:4px 10px;
    border-radius:999px; line-height:1.2; white-space:nowrap;
    font-size:9px; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
}
.mt-status::before { content:""; width:6px; height:6px; border-radius:50%; background:currentColor; flex:0 0 auto; }
.mt-status.is-scheduled { background:#e8f0ff; color:#245cbb; }
.mt-status.is-completed { background:#e9f7f0; color:#137a4e; }
.mt-status.is-postponed { background:#fff4e0; color:#a16207; }
.mt-status.is-cancelled { background:#fdecec; color:#b91c1c; }
.mt-status.is-live { background:#fdecec; color:#b91c1c; }
.mt-status.is-ended,
.mt-status.is-none,
.mt-status.is-default { background:#eef1f6; color:#5b6577; }
.mt-status.is-live::before {
    box-shadow:0 0 0 3px rgba(185,28,28,.14);
    animation:mtPulse 1.6s ease-in-out infinite;
}

/* ---------- Stream preview & public link -------------------------------- */
.mt-preview-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
.mt-preview-platform { display:inline-flex; align-items:center; gap:7px; font-size:10.5px; font-weight:600; color:#5b6577; }
.mt-preview-platform i { font-size:12px; color:var(--mt-red); }
.mt-preview-link {
    display:inline-flex; align-items:center; gap:5px;
    font-size:10px; font-weight:600; color:#245cbb; text-decoration:none;
}
.mt-preview-link:hover { color:var(--mt-navy); text-decoration:underline; }
.mt-preview-link i { font-size:11px; }
.mt-preview { overflow:hidden; border:1px solid #d7dfeb; border-radius:11px; background:#060b17; }
.mt-preview iframe { display:block; width:100%; border:0; }
.mt-watch { margin-top:12px; padding:12px; border:1px solid var(--mt-line); border-radius:10px; background:#fbfcff; }
.mt-watch-label {
    display:inline-flex; align-items:center; gap:5px;
    font-size:9px; font-weight:600; letter-spacing:.13em; text-transform:uppercase; color:#8b95a8;
}
.mt-watch-row { display:flex; align-items:stretch; gap:6px; margin-top:7px; }
.mt-watch-row .form-control {
    flex:1 1 auto; min-width:0; height:38px; padding:7px 10px;
    border:1px solid #dbe2ec; border-radius:8px; background:#fff;
    font-size:11px; color:#41506b; box-shadow:none;
}
.mt-icon-btn {
    display:inline-grid; place-items:center; width:38px; height:38px; flex:0 0 auto;
    border:1px solid #dbe2ec; border-radius:8px; background:#fff; color:#5b6577;
    transition:background .16s ease, border-color .16s ease, color .16s ease;
}
.mt-icon-btn:hover { background:var(--mt-navy); border-color:var(--mt-navy); color:#fff; }

/* ---------- Forms -------------------------------------------------------- */
.mt-form { display:flex; flex-direction:column; gap:14px; }
.mt-form-stack { margin-top:16px; }
.mt-field { min-width:0; }
.mt-form .form-label,
.mt-field-label {
    display:block; margin-bottom:6px; color:#64708a;
    font-size:9.5px; font-weight:600; letter-spacing:.12em; text-transform:uppercase;
}
.mt-form .form-label .mt-required { color:var(--mt-red); }
.mt-form .form-label .mt-optional { color:#b3bccd; font-weight:500; letter-spacing:.04em; text-transform:none; }
.mt-form .form-control,
.mt-form .form-select {
    min-height:38px; padding:8px 11px; border:1px solid #dbe2ec; border-radius:8px;
    background-color:#fff; color:var(--mt-ink); font-size:12px; box-shadow:none;
}
.mt-form .form-control::placeholder { color:#b3bccd; }
.mt-form .form-control:focus,
.mt-form .form-select:focus {
    border-color:var(--mt-blue); box-shadow:0 0 0 3px rgba(47,111,237,.12);
}
.mt-hint {
    display:flex; align-items:flex-start; gap:6px; margin:7px 0 0;
    color:#98a1b2; font-size:10.5px; line-height:1.5;
}
.mt-hint i { margin-top:1px; color:#b3bccd; }
.mt-actions { display:flex; flex-wrap:wrap; gap:8px; }
.mt-field-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
.mt-field-head { display:flex; flex-wrap:wrap; align-items:center; gap:6px 8px; margin-bottom:8px; }
.mt-field-head .mt-field-label { margin-bottom:0; }

/* ---------- Buttons ------------------------------------------------------ */
.mt-btn {
    display:inline-flex; align-items:center; justify-content:center; gap:7px;
    padding:9px 15px; border:1px solid transparent; border-radius:9px;
    font-family:inherit; font-size:11.5px; font-weight:600; letter-spacing:.02em;
    line-height:1.2; cursor:pointer;
    transition:background .16s ease, border-color .16s ease, color .16s ease, box-shadow .16s ease;
}
.mt-btn i { font-size:12px; }
.mt-btn-primary { background:var(--mt-navy); border-color:var(--mt-navy); color:#fff; }
.mt-btn-primary:hover { background:var(--mt-navy-2); border-color:var(--mt-navy-2); color:#fff; }
.mt-btn-danger { background:var(--mt-red); border-color:var(--mt-red); color:#fff; }
.mt-btn-danger:hover { background:#991b1b; border-color:#991b1b; color:#fff; }
.mt-btn-muted { background:#eef1f6; border-color:#e1e6ef; color:#41506b; }
.mt-btn-muted:hover { background:var(--mt-navy); border-color:var(--mt-navy); color:#fff; }
.mt-btn-success { background:var(--mt-green); border-color:var(--mt-green); color:#fff; }
.mt-btn-success:hover { background:#0f5f3e; border-color:#0f5f3e; color:#fff; }
.mt-btn-block { width:100%; margin-top:4px; }

/* ---------- Chips & notes ------------------------------------------------ */
.mt-chip {
    display:inline-flex; align-items:center; padding:3px 8px; border-radius:999px;
    background:var(--mt-soft); color:#64708a; font-size:9px; font-weight:600;
    letter-spacing:.06em; text-transform:uppercase; white-space:nowrap;
}
.mt-chip.is-info {
    background:#e8f0ff; color:#245cbb; font-size:9.5px;
    letter-spacing:.02em; text-transform:none;
}
.mt-note {
    display:flex; align-items:flex-start; gap:9px; margin-bottom:14px;
    padding:11px 13px; border:1px solid #f3e3bf; border-radius:10px;
    background:#fffaf0; color:#8a5a13; font-size:11.5px; line-height:1.5;
}
.mt-note > i { flex:0 0 auto; margin-top:1px; font-size:13px; color:var(--mt-amber); }
.mt-note strong { color:#7c4a05; }
.mt-note-sub { display:block; margin-top:3px; color:#a1701f; }
.mt-note.is-info { border-color:#dbe7f8; background:#f5f8ff; color:#41506b; }
.mt-note.is-info > i { color:var(--mt-blue); }
.mt-note.is-info strong { color:var(--mt-navy); }
.mt-note.is-info a { color:var(--mt-blue); font-weight:600; }
.mt-note.is-info a:hover { text-decoration:underline !important; }
.mt-note.is-success { border-color:#d9ece3; background:#f4faf7; color:#2f6b52; }
.mt-note.is-success > i { color:var(--mt-green); }
.mt-note.is-success strong { color:#0f5f3e; }

/* ---------- Set-score grid ---------------------------------------------- */
.mt-set-table { width:100%; margin:0 0 4px; border:0; border-collapse:collapse; font-size:11.5px; }
.mt-set-table thead th {
    padding:8px 10px; border:0; border-bottom:1px solid var(--mt-line); background:var(--mt-soft);
    color:#64708a; font-size:8.5px; font-weight:600; letter-spacing:.12em;
    text-transform:uppercase; text-align:left;
}
.mt-set-table thead th:not(:first-child) { text-align:center; }
.mt-set-table tbody td { padding:6px 8px; border-bottom:1px solid var(--mt-line-soft); vertical-align:middle; color:#41506b; }
.mt-set-table tbody tr:last-child td { border-bottom:0; }
.mt-set-table .mt-set-label { display:block; font-size:11px; font-weight:600; color:var(--mt-navy); white-space:nowrap; }
.mt-set-table .mt-set-target { display:block; margin-top:1px; font-size:9.5px; color:var(--mt-faint); white-space:nowrap; }
.mt-set-table .form-control {
    width:84px; max-width:100%; height:34px; min-height:0; margin:0 auto; padding:4px 6px;
    border-radius:7px; text-align:center; font-size:11.5px; font-weight:600;
}

/* ---------- Filed result ------------------------------------------------- */
.mt-final {
    display:flex; flex-direction:column; gap:4px; padding:18px;
    border:1px solid #d9ece3; border-radius:12px; background:#f4faf7;
}
.mt-final-label {
    display:inline-flex; align-items:center; gap:6px;
    font-size:9px; font-weight:600; letter-spacing:.13em; text-transform:uppercase; color:var(--mt-green);
}
.mt-final-score {
    display:flex; align-items:baseline; gap:8px;
    font-size:2.2rem; font-weight:600; line-height:1.05; letter-spacing:-.03em; color:var(--mt-navy);
}
.mt-final-score span { font-size:1.3rem; color:#b3bccd; }
.mt-final-score small {
    font-size:10px; font-weight:600; letter-spacing:.08em;
    text-transform:uppercase; color:#8b95a8;
}
.mt-final-meta { margin:3px 0 0; font-size:11px; color:#5b6577; }

/* ---------- Result desk page -------------------------------------------- */
.mt-result-head {
    display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between;
    gap:16px; margin:2px 0 16px;
}
.mt-result-title {
    margin:5px 0 4px; font-size:clamp(1.35rem, 2vw, 1.65rem);
    font-weight:600; letter-spacing:-.02em; line-height:1.2; color:var(--mt-navy);
}
.mt-result-sub { margin:0; max-width:60ch; font-size:12.5px; line-height:1.6; color:var(--mt-faint); }
.mt-result-sub strong { color:#41506b; font-weight:600; }
.mt-result-empty {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:8px; min-height:360px; padding:56px 24px; text-align:center;
    border:1px solid var(--mt-line); border-radius:var(--mt-radius); background:#fff;
    box-shadow:0 2px 4px rgba(13,21,38,.03), 0 26px 50px -44px rgba(13,21,38,.5);
}
.mt-result-empty > i { font-size:2rem; color:#c2cad9; }
.mt-result-empty h1 { margin:4px 0 0; font-size:1.25rem; font-weight:600; color:var(--mt-navy); }
.mt-result-empty p { margin:0; max-width:44ch; font-size:12.5px; line-height:1.6; color:var(--mt-faint); }
.mt-result-empty .mt-btn { margin-top:12px; }

.mt-result-room { border-top:1px solid var(--mt-line); }
.mt-result-panel .mt-btn-block { margin-top:14px; }
.mt-result-panel .mt-final + .mt-hint { margin-top:12px; }

/* ---------- Register panel ---------------------------------------------- */
.mt-register {
    overflow:hidden; scroll-margin-top:18px; background:#fff;
    border:1px solid var(--mt-line); border-radius:var(--mt-radius);
    box-shadow:0 2px 4px rgba(13,21,38,.03), 0 26px 50px -44px rgba(13,21,38,.5);
}
.mt-register-head {
    display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between;
    gap:16px; padding:20px 24px 18px; border-bottom:1px solid var(--mt-line-soft);
}
.mt-register-title {
    display:flex; flex-wrap:wrap; align-items:center; gap:9px;
    margin:4px 0 0; font-size:1.08rem; font-weight:600;
    letter-spacing:-.01em; color:var(--mt-navy);
}
.mt-register-count {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:26px; padding:3px 8px; border-radius:999px;
    background:var(--mt-soft); color:#64708a;
    font-size:9.5px; font-weight:700; letter-spacing:.04em;
}
.mt-register-sub { margin:5px 0 0; font-size:11.5px; color:var(--mt-faint); }
.mt-view-switch { display:inline-flex; gap:3px; padding:3px; border-radius:9px; background:#eef2f7; }
.mt-view-btn {
    display:inline-flex; align-items:center; justify-content:center; gap:6px;
    padding:6px 13px; border:0; border-radius:7px; background:transparent;
    color:var(--mt-muted); font-family:inherit; font-size:11.5px; font-weight:600;
    line-height:1.2; cursor:pointer;
    transition:background .18s ease, color .18s ease, box-shadow .18s ease;
}
.mt-view-btn:hover { color:var(--mt-navy); }
.mt-view-btn.is-active { background:#fff; color:var(--mt-navy); box-shadow:0 2px 6px rgba(9,23,60,.14); }
.mt-view-btn i { font-size:12.5px; }

/* ---------- Register toolbar ------------------------------------------- */
.mt-toolbar {
    display:flex; flex-wrap:wrap; align-items:center; gap:10px;
    padding:12px 24px; border-bottom:1px solid var(--mt-line-soft); background:#fbfcff;
}
.mt-search { position:relative; flex:1 1 260px; max-width:340px; }
.mt-search > i {
    position:absolute; top:50%; left:12px; transform:translateY(-50%);
    color:#a7b1c2; font-size:12px; pointer-events:none;
}
.mt-search input {
    width:100%; height:36px; padding:7px 12px 7px 33px;
    border:1px solid #dbe2ec; border-radius:8px; background:#fff;
    color:var(--mt-ink); font-size:11.5px; box-shadow:none;
}
.mt-search input:focus {
    border-color:var(--mt-blue); box-shadow:0 0 0 3px rgba(47,111,237,.12); outline:none;
}
.mt-toolbar .form-select {
    width:auto; min-width:150px; height:36px; padding:6px 32px 6px 11px;
    border:1px solid #dbe2ec; border-radius:8px; background-color:#fff;
    color:#41506b; font-size:11.5px; box-shadow:none;
}
.mt-toolbar .form-select:focus {
    border-color:var(--mt-blue); box-shadow:0 0 0 3px rgba(47,111,237,.12);
}
.mt-toolbar-meta {
    margin-left:auto; font-size:9.5px; font-weight:600; letter-spacing:.1em;
    text-transform:uppercase; color:#98a1b2; white-space:nowrap;
}

/* ---------- Register table --------------------------------------------- */
.mt-table-wrap { overflow-x:auto; }
.mt-table { width:100%; min-width:1040px; margin:0; border-collapse:collapse; font-size:12.5px; }
.mt-table thead th {
    padding:10px 18px; border:0; border-bottom:1px solid var(--mt-line); background:var(--mt-soft);
    text-align:left; white-space:nowrap; color:#64708a;
    font-size:8.5px; font-weight:600; letter-spacing:.13em; text-transform:uppercase;
}
.mt-table tbody td {
    padding:12px 18px; vertical-align:middle;
    border-bottom:1px solid var(--mt-line-soft); color:#41506b;
}
.mt-table tbody tr:last-child td { border-bottom:0; }
.mt-table tbody tr[data-match-row]:hover { background:#fbfcff; }
.mt-table .mt-col-num { width:52px; }
.mt-table .mt-col-action { width:120px; text-align:right; }
.mt-num { font-size:9.5px; font-weight:600; letter-spacing:.1em; color:#b3bccd; }
.mt-fixture { display:flex; flex-direction:column; gap:6px; min-width:190px; }
.mt-fixture-team { display:flex; align-items:center; gap:8px; min-width:0; }
.mt-fixture-crest {
    display:grid; place-items:center; width:23px; height:23px; flex:0 0 auto;
    border-radius:50%; background:#e8edf7; color:var(--mt-navy);
    font-size:8px; font-weight:700; letter-spacing:.02em;
}
.mt-fixture-team.is-b .mt-fixture-crest { background:#e7f4ee; color:#0f6847; }
.mt-fixture-name {
    min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    font-size:12px; font-weight:600; color:var(--mt-navy);
}
.mt-fixture-team.is-b .mt-fixture-name { color:#41506b; }
.mt-league {
    display:block; max-width:230px; overflow:hidden;
    text-overflow:ellipsis; white-space:nowrap;
    font-size:11.5px; font-weight:600; color:#41506b;
}
.mt-when strong { display:block; font-size:11.5px; font-weight:600; color:#41506b; white-space:nowrap; }
.mt-when span { display:block; margin-top:2px; font-size:10.5px; color:#98a1b2; white-space:nowrap; }
.mt-venue { display:inline-flex; align-items:center; gap:6px; max-width:180px; min-width:0; font-size:11px; color:#5b6577; }
.mt-venue i { flex:0 0 auto; font-size:11px; color:#b3bccd; }
.mt-venue span { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.mt-stream-state {
    display:inline-flex; align-items:center; gap:6px; white-space:nowrap;
    font-size:10px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; color:#7c8698;
}
.mt-stream-state.is-live { color:var(--mt-red); }
.mt-stream-state.is-live .mt-stream-dot {
    box-shadow:0 0 0 3px rgba(185,28,28,.14);
    animation:mtPulse 1.6s ease-in-out infinite;
}
.mt-stream-state.is-scheduled { color:#245cbb; }
.mt-stream-state.is-ended { color:#64708a; }
.mt-stream-state.is-none { color:#98a1b2; }
.mt-stream-dot { width:6px; height:6px; border-radius:50%; background:currentColor; flex:0 0 auto; }
.mt-stream-none { font-size:10.5px; color:#b3bccd; white-space:nowrap; }
.mt-row-btn {
    display:inline-flex; align-items:center; gap:6px; padding:7px 11px;
    border:1px solid var(--mt-line); border-radius:8px; background:#fff;
    color:#4b5563; font-size:10.5px; font-weight:600; white-space:nowrap;
    transition:background .16s ease, border-color .16s ease, color .16s ease;
}
.mt-row-btn:hover { background:var(--mt-navy); border-color:var(--mt-navy); color:#fff !important; }
.mt-row-btn i { font-size:11px; }
.mt-empty {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:5px; padding:46px 20px; text-align:center;
}
.mt-empty > i { font-size:1.7rem; color:#c2cad9; }
.mt-empty-title { font-size:12.5px; font-weight:600; color:var(--mt-navy); }
.mt-empty-sub { max-width:38ch; font-size:11.5px; line-height:1.55; color:var(--mt-faint); }

/* ---------- Calendar ----------------------------------------------------- */
.mt-calendar { padding:18px 24px 24px; }
.mt-cal-legend { display:flex; flex-wrap:wrap; align-items:center; gap:6px 16px; margin-bottom:14px; }
.mt-cal-legend > span {
    display:inline-flex; align-items:center; gap:6px;
    font-size:9px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:#8b95a8;
}
.mt-cal-dot { width:7px; height:7px; border-radius:50%; display:inline-block; flex:0 0 auto; }
.mt-cal-dot.is-scheduled { background:#2f6fed; }
.mt-cal-dot.is-completed { background:#137a4e; }
.mt-cal-dot.is-postponed { background:#b26a12; }
.mt-cal-dot.is-cancelled { background:#b91c1c; }
.mt-cal-dot.is-live { background:#dc2626; }

#committeeCalendar { min-height:520px; font-size:12px; }
#committeeCalendar .fc-toolbar { flex-wrap:wrap; gap:10px; margin-bottom:16px; }
#committeeCalendar .fc-toolbar-title { color:var(--mt-navy); font-size:1rem; font-weight:600; letter-spacing:-.01em; }
#committeeCalendar .fc-button-primary {
    padding:5px 11px; border-radius:7px; background:var(--mt-navy); border-color:var(--mt-navy);
    font-size:11px; font-weight:600; text-transform:capitalize; box-shadow:none;
}
#committeeCalendar .fc-button-primary:hover { background:var(--mt-navy-2); border-color:var(--mt-navy-2); }
#committeeCalendar .fc-button-primary:not(:disabled):active,
#committeeCalendar .fc-button-primary:not(:disabled).fc-button-active { background:var(--mt-blue); border-color:var(--mt-blue); }
#committeeCalendar .fc-button-primary:disabled { background:#94a3b8; border-color:#94a3b8; opacity:1; }
#committeeCalendar .fc-col-header-cell { background:var(--mt-soft); border-color:var(--mt-line); }
#committeeCalendar .fc-col-header-cell-cushion {
    padding:8px 4px; color:#64708a; font-size:8.5px; font-weight:600;
    letter-spacing:.12em; text-transform:uppercase;
}
#committeeCalendar .fc-daygrid-day-number { padding:6px 8px; color:#41506b; font-size:11.5px; font-weight:600; }
#committeeCalendar .fc-day-today { background:#f5f8ff !important; }
#committeeCalendar .fc-event { border:0; border-radius:5px; padding:1px 6px; font-size:10px; font-weight:600; cursor:pointer; }
#committeeCalendar .fc-event:hover { filter:brightness(1.06); }
#committeeCalendar .fc-daygrid-event-dot { display:none; }
#committeeCalendar .fc-daygrid-more-link { color:var(--mt-navy); font-size:10px; font-weight:600; }
#committeeCalendar .fc-popover { border:1px solid var(--mt-line); border-radius:12px; box-shadow:0 20px 40px -26px rgba(7,17,41,.55); }
#committeeCalendar .fc-popover-header { background:#fff; color:var(--mt-navy); font-size:11px; font-weight:600; }
#committeeCalendar .fc-prev-button .fc-icon,
#committeeCalendar .fc-next-button .fc-icon { font-family:inherit !important; font-size:inherit !important; line-height:1 !important; }
#committeeCalendar .fc-prev-button .fc-icon::before,
#committeeCalendar .fc-next-button .fc-icon::before { content:none !important; }
#committeeCalendar .fc-prev-button .fc-icon .bi,
#committeeCalendar .fc-next-button .fc-icon .bi { font-size:13px; line-height:1; vertical-align:middle; }

/* ---------- Responsive -------------------------------------------------- */
@media (max-width:1199.98px) {
    .mt-room-grid { grid-template-columns:minmax(0,1fr); }
    .mt-panel + .mt-panel { border-left:0; border-top:1px solid var(--mt-line-soft); }
}
@media (max-width:991.98px) {
    .mt-hero { padding:26px 28px; }
    .mt-hero::after { display:none; }
    .mt-hero-side { flex:1 1 100%; max-width:none; }
}
@media (max-width:767.98px) {
    .committee-matches .mt-page { padding-bottom:20px; }
    .mt-hero { gap:18px; padding:22px 20px; border-radius:12px; }
    .mt-hero-title { font-size:1.45rem; }
    .mt-result-head { gap:12px; margin-bottom:14px; }
    .mt-result-title { font-size:1.3rem; }
    .mt-result-sub { font-size:12px; }
    .mt-result-empty { min-height:300px; padding:42px 18px; }
    .mt-result-empty h1 { font-size:1.1rem; }
    .mt-crumbs { gap:6px; font-size:11.5px; }
    .mt-room-head { padding:20px 16px 18px; }
    .mt-room-faceoff { gap:14px; padding:14px 16px; border-radius:14px; }
    .mt-versus-team { padding:0 12px; }
    .mt-room-team-name { font-size:.98rem; max-width:170px; }
    .mt-crest { width:48px; height:48px; font-size:11px; box-shadow:0 0 0 4px #f8fafc, 0 0 0 5px #c9d9f2; }
    .mt-versus-team.is-b .mt-crest { box-shadow:0 0 0 4px #f8fafc, 0 0 0 5px #c4e3d2; }
    .mt-panel { padding:18px 18px 20px; }
    .mt-room-meta { gap:5px 10px; }
    .mt-register-head { padding:18px; }
    .mt-view-switch { width:100%; }
    .mt-view-btn { flex:1 1 0; }
    .mt-toolbar { padding:12px 18px; }
    .mt-search { max-width:none; flex:1 1 100%; }
    .mt-toolbar .form-select { flex:1 1 0; min-width:0; }
    .mt-toolbar-meta { margin-left:0; }
    .mt-table thead th { padding:9px 14px; }
    .mt-table tbody td { padding:12px 14px; }
    .mt-calendar { padding:16px 18px 20px; }
    .mt-field-grid { grid-template-columns:minmax(0,1fr); }
    #committeeCalendar { min-height:460px; }
}
@media (max-width:600px) {
    .mt-room-faceoff { grid-template-columns:1fr; gap:10px; max-width:100%; padding:16px; }
    .mt-room-faceoff::before { display:none; }
    .mt-versus-team { padding:0; }
    .mt-room-vs { justify-self:center; }
}
@media (max-width:479.98px) {
    .mt-toolbar .form-select { flex:1 1 100%; }
    .mt-actions .mt-btn { flex:1 1 100%; }
}
@media (prefers-reduced-motion: reduce) {
    .mt-hero, .mt-hero-cta, .mt-btn, .mt-row-btn, .mt-view-btn { transition:none; }
    .mt-live-dot,
    .mt-status.is-live::before,
    .mt-stream-state.is-live .mt-stream-dot { animation:none; }
}
</style>
