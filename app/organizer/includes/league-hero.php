<?php
require_once __DIR__ . '/../../../includes/storageService.php';
require_once __DIR__ . '/../../../config/leagueStatus.php';
$heroLeague = $leagueHeroData;
$leagueNameSafe = htmlspecialchars((string) ($heroLeague['league_name'] ?? ''));
$leagueStatus = getLeagueStatus($heroLeague);
$statusLabel = getLeagueStatusLabel($leagueStatus);
$statusPill = 'lp-pill-' . strtolower($leagueStatus);
$publication = trim((string) ($heroLeague['publication_status'] ?? ''));
$heroRaw = (string) ($heroLeague['league_image'] ?? '');
$heroFile = basename($heroRaw);
$heroImage = $heroRaw !== '' && StorageService::isCloudinary($heroRaw)
    ? $heroRaw
    : (($heroFile !== '' && is_file(__DIR__ . '/../../../assets/uploads/' . $heroFile))
        ? '../../assets/uploads/' . $heroFile : '../../assets/images/default-league.svg');
$heroDate = static function ($value) {
    $ts = trim((string) $value) !== '' ? strtotime((string) $value) : false;
    return $ts ? date('M d, Y', $ts) : '—';
};
$leagueDateRange = $heroDate($heroLeague['start_date'] ?? '') . ' – ' . $heroDate($heroLeague['end_date'] ?? '');
$regisDateRange = $heroDate($heroLeague['regis_start'] ?? '') . ' – ' . $heroDate($heroLeague['regis_end'] ?? '');
$sportLabel = trim((string) ($heroLeague['sport'] ?? '')) ?: '—';
$divisionLabel = trim((string) ($heroLeague['division'] ?? '')) ?: '—';
$categoryLabel = trim((string) ($heroLeague['category'] ?? '')) ?: '—';
$approved_count = (int) $leagueHeroApprovedCount;
$maxTeams = max(1, (int) ($heroLeague['max_team'] ?? 1));
$slotsFull = $approved_count >= $maxTeams;
?>
                        <!-- ==================== HERO BANNER ==================== -->
                        <div class="lp-hero dash-animate">
                            <div class="lp-hero-bg" style="background-image:url('<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>');"></div>
                            <div class="lp-hero-inner">
                                <div class="lp-hero-top">
                                    <span class="lp-pill <?= $statusPill ?>"><i class="bi bi-broadcast"></i><?= htmlspecialchars($statusLabel) ?></span>
                                    <span class="lp-pill lp-pill-ghost"><i class="bi bi-dribbble"></i><?= htmlspecialchars($sportLabel) ?></span>
                                    <span class="lp-pill lp-pill-ghost"><i class="bi bi-diagram-3"></i><?= htmlspecialchars($divisionLabel) ?></span>
                                    <span class="lp-pill lp-pill-ghost"><i class="bi bi-gender-ambiguous"></i><?= htmlspecialchars($categoryLabel) ?></span>
                                    <?php if ($publication !== '' && strtolower($publication) !== 'published'): ?>
                                        <span class="lp-pill lp-pill-draft"><i class="bi bi-eye-slash"></i><?= htmlspecialchars($publication) ?></span>
                                    <?php endif; ?>
                                </div>

                                <h1 class="lp-hero-title"><?= $leagueNameSafe ?></h1>
                               

                                <div class="lp-hero-bottom">
                                    <div class="lp-hero-meta">
                                        <div class="lp-meta-item"><i class="bi bi-calendar3"></i><span><small>League dates</small><?= $leagueDateRange ?></span></div>
                                        <div class="lp-meta-item"><i class="bi bi-hourglass-split"></i><span><small>Registration</small><?= $regisDateRange ?></span></div>
                                        <div class="lp-meta-item"><i class="bi bi-people"></i><span><small>Team slots</small><?= $approved_count ?> / <?= $maxTeams ?><?= $slotsFull ? ' · filled' : '' ?></span></div>
                                        
                                    </div>

                                </div>
                            </div>
                        </div>
