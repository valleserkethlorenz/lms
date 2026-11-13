<?php
/**
 * Create League (Organizer) — league creation as a page instead of a dialog.
 *
 * The form still posts to action/leagueAction.php with the `regisLeague`
 * marker, so the save path is unchanged: the league is always created as a
 * Draft and publishing stays a separate decision on the league register
 * (league.php).
 *
 * Layout: the shared organizer breadcrumb + hero (includes/page-hero.php) so
 * this screen reads as part of the league desk instead of a detached form.
 */
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
/** @var mysqli $conn */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$userId = currentUserId();
$full = currentUserName();
$userType = currentRole();
requireRole([1]);
requireActiveAccount($conn);

/* Sport / division / venue / category / document-type choices come from the
   active utilities the admin maintains, grouped by their category. */
$utilities = ['sport' => [], 'division' => [], 'venue' => [], 'category' => [], 'document_type' => []];
$utilityResult = $conn->query("SELECT id,category,name,min_age,max_age,default_min_players,default_max_players,applies_to FROM tbl_utility WHERE is_active=1 ORDER BY category,name");
if ($utilityResult) {
    while ($utility = $utilityResult->fetch_assoc()) {
        if (isset($utilities[$utility['category']])) {
            $utilities[$utility['category']][] = $utility;
        }
    }
}

$loggin = true;
$time = strtotime('-10 year', time());
$date = date('Y-m-d', $time);
$today = date('M/d/Y');
include 'includes/header.php';
?>
    <body id="page-top" class="body">
        <?php include 'includes/bar.php'; ?>

        <div class="container-fluid lp-page" style="padding-top:20px;min-height:800px;">
            <div class="row p-3">
                <div class="card cards" style="background:transparent;box-shadow:none;border:none;">
                    <div class="card-body p-0">
                        <?php include 'includes/dashboard-styles.php'; ?>
                        <?php include 'includes/league-view-styles.php'; ?>

                        <style>
                            
                            .cl-card { margin-bottom:20px; padding:22px 24px 26px; border:1px solid var(--lp-border); border-radius:12px; background:#fff; box-shadow:0 6px 22px rgba(9,23,60,.06); }
                            .cl-card-head { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; margin:0 0 18px; padding-bottom:14px; border-bottom:1px solid #eef2f7; }
                            .cl-card-title { display:flex; align-items:center; gap:10px; margin:0; font-size:1.25rem; font-weight:700; color:var(--lp-navy); }
                            .cl-card-icon { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:34px; height:34px; border-radius:9px; background:var(--lp-soft); color:var(--lp-blue); font-size:.95rem; }
                            
                            .cl-group { display:flex; align-items:center; gap:8px; margin:10px 0 0; padding-bottom:8px; border-bottom:1px solid #eef2f7; font-size:11px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:#75809a; }
                            .cl-group i { color:var(--lp-blue); }
                            .cl-form-note { display:flex; align-items:center; gap:8px; padding:9px 14px; border:1px solid #dbeafe; border-radius:9px; background:#f0f7ff; font-size:12.5px; color:#1e3a68; }
                            .cl-form-note strong { font-weight:600; }
                            .cl-doc-list .input-group { margin-bottom:8px; }
                            .cl-req-table { border:1px solid var(--lp-border); border-radius:10px; overflow:hidden; }
                            .cl-req-head { padding:10px 16px; border-bottom:1px solid #eef2f7; background:#f8fafc; font-size:11px; font-weight:600; letter-spacing:.5px; text-transform:uppercase; color:var(--lp-muted); }
                            .cl-req-row { padding:12px 16px; border-bottom:1px solid #f1f5f9; }
                            .cl-req-row:last-child { border-bottom:0; }
                            .cl-req-empty { padding:16px; font-size:12.5px; color:var(--lp-muted); }
                            .cl-actions { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin-top:14px; padding-top:18px; border-top:1px solid #eef2f7; }
                            .cl-actions .btn { padding:9px 22px; border-radius:9px; font-size:13px; font-weight:600; }
                            .cl-actions-note { font-size:12px; color:var(--lp-muted); }
                            @media (max-width:767.98px) { .cl-card { padding:18px 16px 20px; } }
                        </style>

                        <!-- ==================== BREADCRUMB ==================== -->
                        <nav class="lp-crumbs dash-animate" aria-label="Breadcrumb">
                            <a href="league.php"><i class="bi bi-arrow-left"></i> League Management</a>
                            <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
                            <span class="lp-crumb-current">Create League</span>
                        </nav>

                       

                        <div class="cl-card dash-animate dash-animate-d2">
                            <div class="cl-card-head">
                                <h2 class="cl-card-title">
                                    <span class="cl-card-icon" aria-hidden="true"><i class="bi bi-clipboard-check"></i></span>
                                    <span>League details</span>
                                </h2>
                                <span class="lp-row-status is-neutral">Draft</span>
                            </div>

                            <form class="row g-3 lp-form" id="regisLeague" autocomplete="off" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                                <div class="col-12"><h3 class="cl-group"><i class="bi bi-card-heading" aria-hidden="true"></i>League identity</h3></div>
                                <div class="col-md-12">
                                    <label for="league_image" class="form-label">League Image<sup class="text-danger">*</sup>:</label>
                                    <input type="file" name="league_image" id="league_image" class="form-control" accept="image/*" required>
                                    <div class="form-text">JPG, PNG, GIF, WEBP or AVIF.</div>
                                </div>
                                <div class="col-md-12">
                                    <label for="league_name" class="form-label">League Name<sup class="text-danger">*</sup>:</label>
                                    <input name="league_name" class="form-control" id="league_name" type="text" maxlength="255" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="sport" class="form-label">Sport<sup class="text-danger">*</sup>:</label>
                                    <select name="sport" id="sport" class="form-select" required onchange="updateSportRosterDefaults()">
                                        <option value="" selected disabled>Select Sport</option>
                                        <?php foreach ($utilities['sport'] as $utility): ?>
                                        <option value="<?= htmlspecialchars($utility['name'], ENT_QUOTES, 'UTF-8') ?>" data-default-min-players="<?= htmlspecialchars((string) ($utility['default_min_players'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-default-max-players="<?= htmlspecialchars((string) ($utility['default_max_players'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($utility['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="division" class="form-label">Division<sup class="text-danger">*</sup>:</label>
                                    <select name="division" id="division" class="form-select" required onchange="updateEligibilityDisplay()">
                                        <option value="" selected disabled>Select Division</option>
                                        <?php foreach ($utilities['division'] as $utility): ?>
                                        <option value="<?= htmlspecialchars($utility['name'], ENT_QUOTES, 'UTF-8') ?>" data-min-age="<?= (int) $utility['min_age'] ?>" data-max-age="<?= (int) $utility['max_age'] ?>"><?= htmlspecialchars($utility['name'], ENT_QUOTES, 'UTF-8') ?> (<?= (int) $utility['min_age'] ?>–<?= (int) $utility['max_age'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="category" class="form-label">Category<sup class="text-danger">*</sup>:</label>
                                    <select name="category" id="category" class="form-select" required>
                                        <option value="" selected disabled>Select category</option>
                                        <?php foreach ($utilities['category'] as $utility): ?>
                                        <option value="<?= htmlspecialchars($utility['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($utility['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12"><h3 class="cl-group"><i class="bi bi-calendar3" aria-hidden="true"></i>Schedule &amp; venue</h3></div>
                                <div class="col-md-3">
                                    <label for="regis_start" class="form-label">Registration Start<sup class="text-danger">*</sup>:</label>
                                    <input name="regis_start" class="form-control" id="regis_start" type="date" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="regis_end" class="form-label">Registration End<sup class="text-danger">*</sup>:</label>
                                    <input name="regis_end" class="form-control" id="regis_end" type="date" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label">Start Date<sup class="text-danger">*</sup>:</label>
                                    <input name="start_date" class="form-control" id="start_date" type="date" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="end_date" class="form-label">End Date<sup class="text-danger">*</sup>:</label>
                                    <input name="end_date" class="form-control" id="end_date" type="date" required>
                                </div>
                                <div class="col-12">
                                    <div class="form-text">Registration start → registration end → league start → league end. Any other order is rejected.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="venue" class="form-label">Venue<sup class="text-danger">*</sup>:</label>
                                    <select name="venue" class="form-select" id="venue" required>
                                        <option value="" selected disabled>Select Venue</option>
                                        <?php foreach ($utilities['venue'] as $utility): ?>
                                        <option value="<?= htmlspecialchars($utility['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($utility['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12"><h3 class="cl-group"><i class="bi bi-people" aria-hidden="true"></i>Team slots &amp; roster</h3></div>
                                <div class="col-md-4">
                                    <label for="max_team" class="form-label">Max Teams<sup class="text-danger">*</sup>:</label>
                                    <input name="max_team" class="form-control" id="max_team" type="number" min="2" max="256" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="min_player" class="form-label">Min Players<sup class="text-danger">*</sup>:</label>
                                    <input name="min_player" class="form-control" id="min_player" type="number" min="1" max="100" value="5" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="max_player" class="form-label">Max Players<sup class="text-danger">*</sup>:</label>
                                    <input name="max_player" class="form-control" id="max_player" type="number" min="1" max="100" value="15" required>
                                </div>
                                <div class="col-12">
                                    <div class="form-text" id="rosterSizeHint">Teams may register 5–15 players. Set both boxes to the same number for a fixed roster (e.g. Pickleball: 2 and 2).</div>
                                </div>

                                <div class="col-12"><h3 class="cl-group"><i class="bi bi-file-text" aria-hidden="true"></i>Description</h3></div>
                                <div class="col-md-12">
                                    <label for="descrip" class="form-label">About League<sup class="text-danger">*</sup>:</label>
                                    <textarea name="descrip" id="descrip" class="form-control" rows="3" required></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label for="add_info" class="form-label">Other Information (optional):</label>
                                    <textarea name="add_info" id="add_info" class="form-control" maxlength="255" rows="2"></textarea>
                                </div>

                                <div class="col-12"><h3 class="cl-group"><i class="bi bi-paperclip" aria-hidden="true"></i>Guidelines &amp; requirements</h3></div>
                                <div class="col-md-12">
                                    <label class="form-label">League Guideline Documents (optional):</label>
                                    <div id="documentsContainer" class="cl-doc-list">
                                        <div class="input-group" id="documentInput_1">
                                            <input type="file" name="documents[]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png">
                                            <button class="btn btn-outline-danger" type="button" onclick="removeDocumentInput(1)" style="display:none;" id="removeBtn_1">Remove</button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addDocumentInput()">
                                        <i class="bi bi-plus-circle me-1"></i>Add Document
                                    </button>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-label fw-semibold mb-2">Registration Document Requirements</div>
                                    <div class="cl-req-table">
                                        <?php if (!$utilities['document_type']): ?>
                                        <div class="cl-req-empty">No active document types are configured.</div>
                                        <?php else: ?>
                                        <div class="cl-req-head row g-0 d-none d-md-flex">
                                            <div class="col-md-4">Document type</div>
                                            <div class="col-md-3">Scope</div>
                                            <div class="col-md-5">Instructions</div>
                                        </div>
                                        <?php foreach ($utilities['document_type'] as $utility): ?>
                                        <div class="cl-req-row row g-3 align-items-center requirement-row">
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input doc-required-toggle" type="checkbox" name="document_requirement[<?= (int) $utility['id'] ?>]" value="1" id="doc_req_<?= (int) $utility['id'] ?>">
                                                    <label class="form-check-label fw-semibold" for="doc_req_<?= (int) $utility['id'] ?>"><?= htmlspecialchars($utility['name'], ENT_QUOTES, 'UTF-8') ?></label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label fw-semibold d-md-none">Scope</label>
                                                <?php
                                                $scopeLabel = $utility['applies_to'] === 'team' ? 'Per Team' : 'Per Player';
                                                $scopeIcon  = $utility['applies_to'] === 'team' ? 'bi-people-fill' : 'bi-person-fill';
                                                $scopeBg    = $utility['applies_to'] === 'team' ? '#fef3c7' : '#dbeafe';
                                                $scopeColor = $utility['applies_to'] === 'team' ? '#92400e' : '#1e40af';
                                                ?>
                                                <input type="hidden" name="document_requirement_scope[<?= (int) $utility['id'] ?>]" value="<?= htmlspecialchars($utility['applies_to'] ?? 'player', ENT_QUOTES, 'UTF-8') ?>">
                                                <span class="badge" style="background:<?= $scopeBg ?>;color:<?= $scopeColor ?>;font-size:.85rem;padding:6px 12px;"><i class="bi <?= $scopeIcon ?> me-1"></i><?= $scopeLabel ?></span>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label fw-semibold d-md-none">Instructions</label>
                                                <input type="text" name="document_instructions[<?= (int) $utility['id'] ?>]" class="form-control" maxlength="500" placeholder="Optional notes or file instructions">
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-12 cl-actions">
                                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Create League</button>
                                    <a class="lp-link-btn" href="league.php"><i class="bi bi-x-circle" aria-hidden="true"></i>Cancel</a>
                                    
                                </div>
                            </form>
                        </div>

                        <?php include 'includes/footer.php'; ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
        /* ----------------------------- Create League ------------------------------
           Client-side helpers for the draft form. They are usability aids only —
           action/leagueAction.php re-validates every field server-side. */

        /* Division drives the age note under the identity block. */
        function updateEligibilityDisplay() {
            var select = document.getElementById('division');
            var note = document.getElementById('eligibilityAlert');
            var range = document.getElementById('ageRange');
            if (!select || !note || !range) return;
            var option = select.selectedOptions[0];
            if (option && option.dataset.minAge !== undefined) {
                range.textContent = option.dataset.minAge + '–' + option.dataset.maxAge + ' years';
                note.style.display = '';
            } else {
                note.style.display = 'none';
            }
        }

        /* Picking a sport prefills the roster range the admin configured. */
        function updateSportRosterDefaults() {
            var select = document.getElementById('sport');
            var option = select && select.selectedOptions[0];
            if (!option) return;
            var minimum = Number(option.dataset.defaultMinPlayers);
            var maximum = Number(option.dataset.defaultMaxPlayers);
            if (Number.isInteger(minimum) && Number.isInteger(maximum) && minimum >= 1 && maximum >= minimum && maximum <= 100) {
                document.getElementById('min_player').value = minimum;
                document.getElementById('max_player').value = maximum;
            }
            updateRosterSizeHint();
        }

        // A fixed roster (min === max) must read "exactly N players", never "N–N".
        function rosterSizeHintText(minimum, maximum) {
            if (!Number.isInteger(minimum) || !Number.isInteger(maximum) || minimum < 1 || maximum < 1) {
                return 'Set both boxes to the same number for a fixed roster (e.g. Pickleball: 2 and 2).';
            }
            if (minimum > maximum) {
                return 'Minimum players cannot be greater than maximum players.';
            }
            if (minimum === maximum) {
                return 'Fixed roster size — every team must register exactly ' + minimum + ' player' + (minimum === 1 ? '' : 's') + '.';
            }
            return 'Teams may register ' + minimum + '–' + maximum + ' players.';
        }

        function updateRosterSizeHint() {
            var hint = document.getElementById('rosterSizeHint');
            var minimumInput = document.getElementById('min_player');
            var maximumInput = document.getElementById('max_player');
            if (!hint || !minimumInput || !maximumInput) return;
            hint.textContent = rosterSizeHintText(parseInt(minimumInput.value, 10), parseInt(maximumInput.value, 10));
        }

        /* Guideline documents — the first row is rendered by the page. */
        var documentCount = 1;
        function addDocumentInput() {
            var container = document.getElementById('documentsContainer');
            if (!container) return;
            documentCount++;
            var row = document.createElement('div');
            row.className = 'input-group';
            row.id = 'documentInput_' + documentCount;
            row.innerHTML = '<input type="file" name="documents[]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png">'
                + '<button class="btn btn-outline-danger" type="button" onclick="removeDocumentInput(' + documentCount + ')">Remove</button>';
            container.appendChild(row);
            updateRemoveButtons();
        }
        function removeDocumentInput(id) {
            var row = document.getElementById('documentInput_' + id);
            if (!row) return;
            row.remove();
            updateRemoveButtons();
        }
        function updateRemoveButtons() {
            var rows = document.querySelectorAll('#documentsContainer .input-group');
            rows.forEach(function (row) {
                var button = row.querySelector('button');
                if (button) button.style.display = rows.length > 1 ? '' : 'none';
            });
        }
        updateRemoveButtons();
        </script>

        <script>
        /**
         * Shared post-creation step: the league is saved, so let the organizer
         * choose between setting the competition structure up now or later from
         * the Setup tab. Nothing is enforced either way.
         *
         * @param {Object} res       JSON response from action/leagueAction.php
         * @param {Object} [options] { laterHref: 'league.php' }
         */
        window.lmsAfterLeagueCreated = function (res, options) {
            options = options || {};
            var laterHref = options.laterHref || 'league.php';
            var leagueId = res && res.league_id ? parseInt(res.league_id, 10) : 0;
            if (!leagueId) { window.location.href = laterHref; return; }

            Swal.fire({
                icon: 'success',
                title: 'League created',
                html: 'Saved as a <strong>Draft</strong>.<br>'
                    + '<span style="font-size:13px;color:#6b7280;">Choose the competition structure now, or set it up later from the Setup tab. '
                    + 'Until then the league uses the manual pairing assistant.</span>',
                showDenyButton: true,
                confirmButtonText: 'Setup now',
                denyButtonText: 'Set up later',
                confirmButtonColor: '#123f70',
                denyButtonColor: '#6b7280',
                reverseButtons: true,
                allowOutsideClick: false
            }).then(function (result) {
                window.location.href = result.isConfirmed
                    ? 'setup.php?id=' + leagueId + '#step-format'
                    : laterHref;
            });
        };
        </script>

        <script>
        /* Last client-side gate before the request leaves the page. action/
           leagueAction.php enforces the same rules, so this only saves a
           round-trip on obvious mistakes. */
        (function () {
            var form = document.getElementById('regisLeague');
            if (!form) return;
            var submitButton = form.querySelector('button[type="submit"]');
            var submitLabel = submitButton ? submitButton.innerHTML : '';
            var isSubmitting = false;

            ['min_player', 'max_player'].forEach(function (id) {
                var input = document.getElementById(id);
                if (input) input.addEventListener('input', updateRosterSizeHint);
            });
            updateRosterSizeHint();

            function setBusy(busy, label) {
                if (!submitButton) return;
                submitButton.disabled = busy;
                if (busy) submitButton.textContent = label || 'Creating…';
                else submitButton.innerHTML = submitLabel;
            }

            function validate() {
                var regisStart = document.getElementById('regis_start').value;
                var regisEnd = document.getElementById('regis_end').value;
                var startDate = document.getElementById('start_date').value;
                var endDate = document.getElementById('end_date').value;
                if (!regisStart || !regisEnd || !startDate || !endDate) {
                    return 'Fill in all four dates.';
                }
                var rs = new Date(regisStart), re = new Date(regisEnd);
                var sd = new Date(startDate), ed = new Date(endDate);
                if (!(rs <= re && re <= sd && sd <= ed)) {
                    return 'Dates must run in order: registration start ≤ registration end ≤ start date ≤ end date.';
                }
                var maxTeam = parseInt(document.getElementById('max_team').value, 10);
                if (!Number.isInteger(maxTeam) || maxTeam < 2 || maxTeam > 256) {
                    return 'Max teams must be from 2 to 256.';
                }
                var minPlayer = parseInt(document.getElementById('min_player').value, 10);
                var maxPlayer = parseInt(document.getElementById('max_player').value, 10);
                if (!Number.isInteger(minPlayer) || minPlayer < 1 || minPlayer > 100) {
                    return 'Minimum players must be from 1 to 100.';
                }
                if (!Number.isInteger(maxPlayer) || maxPlayer < 1 || maxPlayer > 100) {
                    return 'Maximum players must be from 1 to 100.';
                }
                if (minPlayer > maxPlayer) {
                    return 'Minimum players cannot be greater than maximum players.';
                }
                return '';
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (isSubmitting) {
                    Swal.fire({icon: 'warning', title: 'Please wait…', text: 'This league is already being created.'});
                    return;
                }
                var problem = validate();
                if (problem) {
                    Swal.fire({icon: 'error', title: 'Check the form', text: problem});
                    return;
                }

                Swal.fire({
                    title: 'Create league?',
                    text: 'The league is saved as a Draft you can still edit.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Create',
                    cancelButtonText: 'Cancel'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    isSubmitting = true;
                    setBusy(true, 'Creating…');

                    var formData = new FormData(form);
                    formData.append('regisLeague', true);
                    $.ajax({
                        url: 'action/leagueAction.php',
                        type: 'post',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: 30000,
                        success: function (data) {
                            isSubmitting = false;
                            var res;
                            try {
                                res = typeof data === 'string' ? JSON.parse(data) : data;
                            } catch (err) {
                                setBusy(false);
                                Swal.fire({icon: 'error', title: 'Server error', text: data});
                                return;
                            }
                            if (res.status == 201) {
                                form.reset();
                                updateRosterSizeHint();
                                updateEligibilityDisplay();
                                updateRemoveButtons();
                                if (typeof window.lmsAfterLeagueCreated === 'function') {
                                    window.lmsAfterLeagueCreated(res, { laterHref: 'league.php' });
                                } else {
                                    window.location.href = 'league.php';
                                }
                            } else {
                                setBusy(false);
                                Swal.fire({icon: res.status >= 500 ? 'error' : 'warning', title: res.msg || 'Unable to create the league.', timer: 8000});
                            }
                        },
                        error: function (xhr, status) {
                            isSubmitting = false;
                            setBusy(false);
                            Swal.fire({icon: 'error', title: 'Error', text: status === 'timeout' ? 'Request timeout. Please check your connection and try again.' : 'Request failed. Please try again.', timer: 8000});
                        }
                    });
                });
            });
        })();
        </script>

    </body>

    </html>
