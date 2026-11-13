<?php
/**
 * Team registration — dedicated page.
 *
 * Replaces the #regisTeam modal that used to live in
 * components/modal/registeam.php. A page keeps the multi-step, document-heavy
 * form safe: refresh, Back and accidental navigation no longer destroy the
 * half-filled registration, and the URL itself is resumable/shareable.
 *
 * Access: Team Representative (role 2) only.
 * The submit endpoint is unchanged (includes/registerTeam.php, JSON).
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../config/leagueStatus.php';
require_once __DIR__ . '/../../includes/registrationGate.php';
require_once __DIR__ . '/../../includes/eligibilityValidator.php';
require_once __DIR__ . '/../../includes/storageService.php';

requireRole([2], getProjectBasePath() . '/login.php');
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = 2;

$leagueId = (int) ($_GET['id'] ?? 0);
$gate     = getRegistrationGate($conn, $leagueId, $userId);

// Unknown, unpublished or archived league → back to the directory.
if ($gate['state'] === 'not_found') {
    header('Location: browse_leagues.php');
    exit;
}

$league_row                = $gate['league'];
$leagueStatus              = $gate['leagueStatus'];
$leagueStatusLabel         = $gate['leagueStatusLabel'];
$registrationLabel         = $gate['registrationLabel'];
$registrationUpcoming      = $gate['registrationUpcoming'];
$regStartTs                = $gate['regStartTs'];
$regEndTs                  = $gate['regEndTs'];
$registeredCount           = $gate['registeredCount'];
$maxTeam                   = $gate['maxTeam'];
$existingApplicationStatus = $gate['existingApplicationStatus'];
$gateState                 = $gate['state'];
$isFormOpen                = ($gateState === 'open');
$regOpen                   = (bool) ($gate['regOpen'] ?? false);
$slotsFull                 = (bool) ($gate['slotsFull'] ?? false);
$leagueStatusPill          = (string) ($gate['leagueStatusPill'] ?? 'lp-pill-ghost');

$leagueName = (string) ($league_row['league_name'] ?? '');
$leagueNameSafe = htmlspecialchars($leagueName, ENT_QUOTES, 'UTF-8');

/* -------------------------------------------------------------------------
   League hero context.
   Mirrors includes/league_header.php (the public league pages) so the
   registration hero reads exactly like the Overview hero on team_information.
   ------------------------------------------------------------------------- */
$heroImgName = (string) ($league_row['league_image'] ?? '');
$heroImgRel  = 'assets/uploads/' . basename($heroImgName);
if (StorageService::isCloudinary($heroImgName)) {
    $heroImgSrc = $heroImgName;
} elseif ($heroImgName !== '' && is_file(__DIR__ . '/../../' . $heroImgRel)) {
    // This page sits three folders below the lms/ root, so the upload folder
    // is addressed through the project base path instead of a relative URL.
    $heroImgSrc = getProjectBasePath() . '/' . $heroImgRel;
} else {
    $heroImgSrc = getProjectBasePath() . '/assets/images/default-league.svg';
}

$heroDateFormat = static function ($value) {
    $ts = trim((string) $value) !== '' ? strtotime((string) $value) : false;
    return $ts ? date('M d, Y', $ts) : '—';
};
$heroLeagueDates = $heroDateFormat($league_row['start_date'] ?? '') . ' – ' . $heroDateFormat($league_row['end_date'] ?? '');
$heroRegisDates  = $heroDateFormat($league_row['regis_start'] ?? '') . ' – ' . $heroDateFormat($league_row['regis_end'] ?? '');
$heroSport       = trim((string) ($league_row['sport'] ?? '')) ?: '—';
$heroDivision    = trim((string) ($league_row['division'] ?? '')) ?: '—';
$heroCategory    = trim((string) ($league_row['category'] ?? '')) ?: '—';
$heroVenue       = trim((string) ($league_row['venue'] ?? ''));

// CSRF token — same contract as the old modal.
if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Eligibility + document requirements (identical queries to the modal).
$requirements         = null;
$documentRequirements = [];
if ($isFormOpen) {
    $requirements = getEligibilityRequirements($conn, $leagueId);

    $docStmt = $conn->prepare("SELECT r.id,r.applies_to,r.is_required,r.instructions,u.name FROM tbl_league_document_requirement r JOIN tbl_utility u ON u.id=r.document_type_id WHERE r.league_id=? ORDER BY r.applies_to,u.name");
    if ($docStmt) {
        $docStmt->bind_param('i', $leagueId);
        $docStmt->execute();
        $docRes = $docStmt->get_result();
        while ($doc = $docRes->fetch_assoc()) {
            $documentRequirements[] = $doc;
        }
        $docStmt->close();
    }
}

$slotText = $maxTeam > 0
    ? $registeredCount . ' of ' . $maxTeam . ' taken'
    : $registeredCount . ' registered';

include 'includes/header.php';
include 'includes/registration-page-styles.php';
// League hero design layer (breadcrumb / hero / pills / buttons). The public
// league pages load the very same file through includes/league_header.php, so
// the registration hero and the Overview hero can never drift apart.
require_once __DIR__ . '/../../includes/league-view-styles.php';
?>
<body id="page-top" class="body">
<?php include 'includes/bar.php'; ?>
<div class="reg-page">
    <div class="lp-page reg-hero">
        <nav class="lp-crumbs" aria-label="Breadcrumb">
            <a href="browse_leagues.php"><i class="bi bi-arrow-left"></i> Browse League</a>
            <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
            <a href="../../team_information.php?id=<?= (int) $leagueId ?>"><?= $leagueNameSafe ?></a>
            <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
            <span class="lp-crumb-current">Team registration</span>
        </nav>

        <header class="lp-hero">
            <div class="lp-hero-bg" style="background-image:url('<?= htmlspecialchars($heroImgSrc, ENT_QUOTES, 'UTF-8') ?>')"></div>
            <div class="lp-hero-inner">
                <div class="lp-hero-top">
                    <span class="lp-pill <?= htmlspecialchars($leagueStatusPill, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-broadcast"></i><?= htmlspecialchars($leagueStatusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="lp-pill lp-pill-ghost"><i class="bi bi-dribbble"></i><?= htmlspecialchars($heroSport, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="lp-pill lp-pill-ghost"><i class="bi bi-diagram-3"></i><?= htmlspecialchars($heroDivision, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="lp-pill lp-pill-ghost"><i class="bi bi-gender-ambiguous"></i><?= htmlspecialchars($heroCategory, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($slotsFull): ?>
                        <span class="lp-pill lp-pill-ghost"><i class="bi bi-people-fill"></i>Slots full</span>
                    <?php endif; ?>
                </div>

                <h1 class="lp-hero-title"><?= $leagueNameSafe ?></h1>

                <div class="lp-hero-bottom">
                    <div class="lp-hero-meta">
                        <div class="lp-meta-item">
                            <i class="bi bi-calendar3"></i>
                            <span><small>League dates</small><?= htmlspecialchars($heroLeagueDates, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="lp-meta-item">
                            <i class="bi bi-hourglass-split"></i>
                            <span><small>Registration</small><?= htmlspecialchars($heroRegisDates, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <?php if ($heroVenue !== ''): ?>
                            <div class="lp-meta-item">
                                <i class="bi bi-geo-alt"></i>
                                <span><small>Venue</small><?= htmlspecialchars($heroVenue, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="lp-meta-item">
                            <i class="bi bi-people"></i>
                            <span><small>Team slots</small><?= htmlspecialchars($slotText, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <div class="lp-hero-actions">
                        <?php if ($regOpen && !$slotsFull): ?>
                            <div id="regCountdown" class="lp-countdown">
                                Registration closes in <strong id="regTime">--</strong>
                            </div>
                        <?php else: ?>
                            <button class="lp-btn lp-btn-state" type="button" disabled>
                                <i class="bi bi-lock-fill"></i><?= htmlspecialchars($registrationLabel, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>
    </div>

    

        <?php if (!$isFormOpen): ?>
            <?php
            $stateIcon  = 'bi-lock-fill';
            $stateTitle = 'Registration is not open';
            $stateText  = 'Registration for this league is not open right now. Check the registration window above or browse other published leagues.';
            $stateActions = '<a class="btn btn-primary" href="browse_leagues.php"><i class="bi bi-search me-1"></i>Browse leagues</a>'
                . '<a class="btn btn-outline-secondary" href="../../team_information.php?id=' . (int) $leagueId . '"><i class="bi bi-box-arrow-up-right me-1"></i>View league page</a>';

            if ($gateState === 'upcoming') {
                $stateIcon  = 'bi-clock-history';
                $stateTitle = 'Registration opens soon';
                $stateText  = 'Registration for ' . $leagueName . ' opens on ' . date('F j, Y', $regStartTs) . '.';
            } elseif ($gateState === 'full') {
                $stateIcon  = 'bi-people-fill';
                $stateTitle = 'All team slots are taken';
                $stateText  = 'This league has reached its maximum of ' . (int) $maxTeam . ' teams. Contact the organizer in case a slot frees up.';
            } elseif ($gateState === 'already_registered') {
                $stateIcon  = 'bi-check2-circle';
                $stateTitle = 'You already have an application';
                $stateText  = 'Your application for this league is currently marked "' . $existingApplicationStatus . '". You can track it from your applications page.';
                $stateActions = '<a class="btn btn-primary" href="league.php"><i class="bi bi-list-check me-1"></i>View my applications</a>'
                    . '<a class="btn btn-outline-secondary" href="browse_leagues.php"><i class="bi bi-search me-1"></i>Browse leagues</a>';
            } elseif ($gateState === 'closed') {
                $stateIcon  = 'bi-lock-fill';
                $stateTitle = 'Registration closed';
                $stateText  = $regEndTs && time() > $regEndTs
                    ? 'Registration for this league closed on ' . date('F j, Y', $regEndTs) . '.'
                    : 'The organizer has closed registration for this league.';
            }
            ?>
            <div class="reg-state">
                <span class="reg-state-icon"><i class="bi <?= htmlspecialchars($stateIcon, ENT_QUOTES, 'UTF-8') ?>"></i></span>
                <h2><?= htmlspecialchars($stateTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars($stateText, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="reg-state-actions"><?= $stateActions ?></div>
            </div>
        <?php else: ?>
        <div class="reg-body">
            <div id="draftRestoreBanner" class="alert d-none" role="status">
                <strong><i class="bi bi-clock-history me-1"></i>Draft restored</strong>
                <div id="draftRestoreText" class="small mt-1"></div>
                <div id="draftFileChecklist" class="small mt-2 d-none">
                    <span class="fw-semibold" id="draftFileChecklistTitle">Re-attach these files before submitting:</span>
                    <ul class="mb-0 mt-1" id="draftFileChecklistList"></ul>
                </div>
            </div>

            <form id="registerTeam">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" value="<?= (int) $leagueId ?>">
                <input type="hidden" name="userId" value="<?= (int) $userId ?>">
                <input type="hidden" name="register" value="1">

                

                <section class="registration-section">
                    <div class="section-heading">
                        <div class="section-heading-main">
                            <span class="section-number">1</span>
                            <div><h6>Team Profile and Documents</h6><p>Enter the official team name and attach the required paperwork.</p></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <label for="team_name" class="form-label">Official Team Name <sup class="text-danger">*</sup></label>
                            <input name="team_name" class="form-control" id="team_name" type="text" minlength="2" maxlength="100" placeholder="Enter the official team name" required>
                        </div>
                        <div class="col-lg-5">
                            <label for="team_image" class="form-label">Team Image <sup class="text-danger">*</sup></label>
                            <input type="file" name="team_image" id="team_image" class="form-control" accept="image/*" required>
                            <div class="form-text">JPG, PNG, WebP, or GIF · Maximum 5 MB.</div>
                        </div>
                        <?php foreach ($documentRequirements as $doc): if ($doc['applies_to'] !== 'team') continue; ?>
                        <div class="col-md-6 document-field-block">
                            <label class="form-label"><?= htmlspecialchars($doc['name']) ?><?php if ($doc['is_required']): ?><span class="required-marker" aria-hidden="true">*</span><span class="visually-hidden"> required</span><?php endif; ?></label>
                            <input type="file" data-document-scope="team" data-document-requirement="<?= (int) $doc['id'] ?>" name="team_documents[<?= (int) $doc['id'] ?>]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.bmp,.webp,application/pdf,image/jpeg,image/png,image/bmp,image/webp" <?= $doc['is_required'] ? 'required' : '' ?>>
                            <?php if ($doc['instructions']): ?><div class="form-text"><?= htmlspecialchars($doc['instructions']) ?></div><?php endif; ?>
                            <div class="form-text">PDF, JPG, PNG, BMP, or WebP · Maximum 10 MB.</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="registration-section">
                    <div class="section-heading">
                        <div class="section-heading-main">
                            <span class="section-number">2</span>
                            <div><h6>Player Roster</h6><p>Add every player who will play for this team in this league.</p></div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm" id="addPlayerButton" onclick="addRow()"><i class="bi bi-plus-circle me-1"></i>Add Player</button>
                            <small class="text-muted" id="playerRowCount" aria-live="polite"></small>
                        </div>
                    </div>

                    <!-- Validation Summary -->
                    <div id="validationSummary" class="alert alert-warning d-none mt-2" role="alert">
                        <strong>Validation Issues:</strong>
                        <ul id="validationList" class="mb-0 mt-2"></ul>
                    </div>

                    <div class="roster-scroll-hint mb-2"><i class="bi bi-arrow-left-right me-1"></i>Swipe horizontally to view all player fields.</div>
                    <div class="table-responsive roster-table-wrap">
                        <table class="table table-bordered roster-table" id="playerTable">
                            <thead>
                                <tr>
                                    <th>Name <span class="required-marker" aria-hidden="true">*</span></th>
                                    <th style="width: 145px;">Birthdate <span class="required-marker" aria-hidden="true">*</span></th>
                                    <th>Role <span class="required-marker" aria-hidden="true">*</span></th>
                                    <th style="width: 100px;">Jersey No. <span class="required-marker" aria-hidden="true">*</span></th>
                                    <th>Gender <span class="required-marker" aria-hidden="true">*</span></th>
                                    <th>Player Documents</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="playerBody">
                                <!-- rows will be generated -->
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="reg-actionbar">
                    <div class="reg-actionbar-meta">
                        <span id="draftStatus" class="is-empty">Draft autosave is on — your entries are saved on this device.</span>
                        <span class="text-muted"><i class="bi bi-shield-lock me-1"></i>Your registration will be securely submitted for Organizer review.</span>
                    </div>
                    <button type="button" id="discardDraftBtn" class="btn btn-link d-none">Discard draft</button>
                    <button type="button" id="saveDraftExitBtn" class="btn btn-secondary"><i class="bi bi-bookmark-check me-1"></i>Save draft &amp; exit</button>
                    <button type="submit" class="btn btn-primary" id="registerTeamSubmit"><i class="bi bi-send-fill me-1"></i>Submit Registration</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<?php if ($regOpen && !$slotsFull): ?>
<script>
    // Same ticker the public league hero runs (includes/league_header.php):
    // the countdown is measured against server time so a client clock that is
    // off by minutes cannot show a closing time that has already passed.
    (function () {
        var regEnd = <?= $regEndTs ? ((int) $regEndTs * 1000) : 0 ?>;
        var serverNow = <?= (int) time() * 1000 ?>;
        var el = document.getElementById('regTime');
        if (!el || !regEnd) return;

        var timeOffset = serverNow - Date.now();

        function formatRemaining(ms) {
            if (ms <= 0) return '0m';
            var sec = Math.floor(ms / 1000);
            var days = Math.floor(sec / 86400);
            sec %= 86400;
            var hours = Math.floor(sec / 3600);
            sec %= 3600;
            var minutes = Math.floor(sec / 60);
            var parts = [];
            if (days > 0) parts.push(days + 'd');
            if (hours > 0) parts.push(hours + 'h');
            parts.push(minutes + 'm');
            return parts.join(' ');
        }

        function update() {
            el.textContent = formatRemaining(regEnd - (Date.now() + timeOffset));
        }

        update();
        setInterval(update, 1000);
    })();
</script>
<?php endif; ?>

<?php if ($isFormOpen): ?>
<script>
window.LMS_REGISTRATION = <?= json_encode([
    'leagueId'        => (int) $leagueId,
    'leagueName'      => $leagueName,
    'userId'          => (int) $userId,
    'api'             => getProjectBasePath() . '/includes/registerTeam.php',
    'today'           => date('Y-m-d'),
    'redirect'        => 'league.php',
    'backUrl'         => '../../team_information.php?id=' . (int) $leagueId,
    'loginUrl'        => getProjectBasePath() . '/login.php',
    'requirements'    => $requirements ?? [],
    'playerDocuments' => array_values(array_filter($documentRequirements, fn($d) => $d['applies_to'] === 'player')),
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="js/register-team.js"></script>
<?php endif; ?>
</body>
</html>
