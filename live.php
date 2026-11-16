<?php
/**
 * Public Livestream Viewer
 * Displays embedded live video from YouTube/Facebook for spectators.
 */
// The viewer is public, but signed-in staff must still be able to get back to
// their own portal, so the session is read here (spectators simply have none).
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config/dbconnect.php';
require_once __DIR__ . '/includes/livestreamHelper.php';
require_once __DIR__ . '/includes/storageService.php';

$matchId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$matchId) { http_response_code(404); die('Match not found.'); }

$stmt = $conn->prepare(
    "SELECT s.id, s.date, s.time, s.venue, s.status, s.livestream_url, s.livestream_status,
            s.a_score, s.b_score, l.league_name, l.sport, l.division,
            ta.team_name AS team_a_name, ta.team_image AS team_a_image,
            tb.team_name AS team_b_name, tb.team_image AS team_b_image
     FROM tbl_schedule s
     JOIN tbl_league l ON l.id = s.league_id
     JOIN tbl_team ta ON ta.id = s.team_a
     JOIN tbl_team tb ON tb.id = s.team_b
     WHERE s.id = ? AND s.published_at IS NOT NULL
       AND s.livestream_url IS NOT NULL AND s.livestream_url != ''
     LIMIT 1"
);
$stmt->bind_param('i', $matchId); $stmt->execute();
$match = $stmt->get_result()->fetch_assoc(); $stmt->close();

if (!$match) { http_response_code(404); die('This match is not available for live viewing.'); }

$parsed = parseLivestreamUrl($match['livestream_url']);
if (!$parsed) { http_response_code(400); die('This match has an unsupported livestream URL.'); }

$isLive = strtolower($match['livestream_status'] ?? '') === 'live';
$pageTitle = $match['team_a_name'] . ' vs ' . $match['team_b_name'] . ' — ' . $match['league_name'];

// Signed-in users return to their own portal; spectators return to the public
// home page. "Open stream" is opened in a new tab, so this link is the only
// way back and must not drop a committee member on the public site.
$backUrl   = 'index.php';
$backLabel = 'Back to Home';
if (!empty($_SESSION['lms_id'])) {
    $portalByRole = [
        1 => ['app/organizer/dashboard.php', 'Back to organizer dashboard'],
        2 => ['app/tr/dashboard.php', 'Back to team dashboard'],
        3 => ['app/admin/dashboard.php', 'Back to admin dashboard'],
        4 => ['app/committee/dashboard.php', 'Back to committee dashboard'],
    ];
    $liveRole = (int) ($_SESSION['lms_role'] ?? 0);
    if (isset($portalByRole[$liveRole])) {
        [$backUrl, $backLabel] = $portalByRole[$liveRole];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | Live Stream</title>
<link href="assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{font-family:'Poppins',sans-serif;}
body{background:#0a0a0a;color:#fff;margin:0;padding:0;min-height:100vh;}
.live-header{background:linear-gradient(135deg,#09173c 0%,#1a3a7c 100%);padding:20px 0;border-bottom:3px solid #dc2626;}
.live-badge{display:inline-flex;align-items:center;gap:6px;background:#dc2626;color:#fff;padding:6px 14px;border-radius:20px;font-weight:700;font-size:.9rem;animation:pulse 2s infinite;}
.live-badge .spinner-grow{width:10px;height:10px;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.7;}}
.video-container{background:#000;max-width:1200px;margin:30px auto;border-radius:12px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.5);}
.match-info-card{background:#1a1a1a;border:1px solid #333;border-radius:12px;padding:24px;max-width:1200px;margin:0 auto 30px;}
.team-display{display:flex;align-items:center;justify-content:space-around;gap:20px;flex-wrap:wrap;}
.team-block{text-align:center;flex:1;min-width:150px;}
.team-logo{width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #333;margin-bottom:10px;background:#fff;}
.team-name{font-size:1.1rem;font-weight:700;color:#fff;}
.vs-divider{font-size:2rem;font-weight:800;color:#666;padding:0 20px;}
.match-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:20px;padding-top:20px;border-top:1px solid #333;}
.meta-item{display:flex;align-items:center;gap:10px;}
.meta-item i{color:#3b82f6;font-size:1.2rem;}
.meta-label{font-size:.75rem;color:#999;text-transform:uppercase;letter-spacing:.5px;}
.meta-value{font-size:.95rem;color:#fff;font-weight:600;}
.score-display{background:#09173c;padding:16px;border-radius:8px;text-align:center;margin-top:20px;}
.score-display .score{font-size:2.5rem;font-weight:800;color:#fff;}
.footer-note{text-align:center;color:#666;font-size:.85rem;padding:20px;max-width:1200px;margin:0 auto;}
.stream-fallback{max-width:1200px;margin:10px auto 20px;padding:0 12px;text-align:right;font-size:.8rem;color:#9aa4b2;}
.stream-fallback a{color:#7fb2ff;text-decoration:none;}
.stream-fallback a:hover{text-decoration:underline;}
@media(max-width:768px){.video-container{margin:15px;}.match-info-card{margin:0 15px 20px;padding:16px;}.team-logo{width:60px;height:60px;}.team-name{font-size:.95rem;}.vs-divider{font-size:1.5rem;padding:0 10px;}.stream-fallback{margin:8px 15px 16px;text-align:left;}}
</style>
</head>
<body>
<div class="live-header">
<div class="container">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
<div><a href="<?= htmlspecialchars($backUrl) ?>" class="text-white text-decoration-none"><i class="bi bi-arrow-left me-2"></i><?= htmlspecialchars($backLabel) ?></a></div>
<?php if ($isLive): ?>
<div class="live-badge"><span class="spinner-grow spinner-grow-sm" role="status"></span>LIVE NOW</div>
<?php else: ?>
<div class="badge bg-secondary px-3 py-2"><?= htmlspecialchars($match['livestream_status'] ?? 'Stream') ?></div>
<?php endif; ?>
</div>
</div>
</div>
<div class="video-container">
<?= livestreamEmbedHtml($parsed['embed_url'], $pageTitle, 675) ?>
</div>
<div class="stream-fallback">
<i class="bi bi-info-circle" aria-hidden="true"></i>
Player not loading? <a href="<?= htmlspecialchars($parsed['original_url']) ?>" target="_blank" rel="noopener">Open this stream on <?= htmlspecialchars(livestreamPlatformLabel($parsed['platform'])) ?></a>
</div>
<div class="match-info-card">
<div class="text-center mb-3">
<small class="text-muted"><?= htmlspecialchars($match['league_name']) ?></small>
<?php if (!empty($match['sport'])): ?><span class="mx-2 text-muted">•</span><small class="text-muted"><?= htmlspecialchars($match['sport']) ?></small><?php endif; ?>
<?php if (!empty($match['division'])): ?><span class="mx-2 text-muted">•</span><small class="text-muted"><?= htmlspecialchars($match['division']) ?></small><?php endif; ?>
</div>
<div class="team-display">
<div class="team-block">
<?php $aImg=$match['team_a_image']??''; $aSrc=StorageService::isCloudinary($aImg)?$aImg:(!empty($aImg)&&file_exists(__DIR__.'/assets/uploads/'.$aImg)?'assets/uploads/'.htmlspecialchars($aImg):''); ?>
<?php if ($aSrc): ?>
<img src="<?= $aSrc ?>" alt="<?= htmlspecialchars($match['team_a_name']) ?>" class="team-logo">
<?php else: ?>
<div class="team-logo d-flex align-items-center justify-content-center bg-light text-dark"><i class="bi bi-people-fill" style="font-size:2rem;"></i></div>
<?php endif; ?>
<div class="team-name"><?= htmlspecialchars($match['team_a_name']) ?></div>
</div>
<div class="vs-divider">VS</div>
<div class="team-block">
<?php $bImg=$match['team_b_image']??''; $bSrc=StorageService::isCloudinary($bImg)?$bImg:(!empty($bImg)&&file_exists(__DIR__.'/assets/uploads/'.$bImg)?'assets/uploads/'.htmlspecialchars($bImg):''); ?>
<?php if ($bSrc): ?>
<img src="<?= $bSrc ?>" alt="<?= htmlspecialchars($match['team_b_name']) ?>" class="team-logo">
<?php else: ?>
<div class="team-logo d-flex align-items-center justify-content-center bg-light text-dark"><i class="bi bi-people-fill" style="font-size:2rem;"></i></div>
<?php endif; ?>
<div class="team-name"><?= htmlspecialchars($match['team_b_name']) ?></div>
</div>
</div>
<?php if (strtolower($match['status'])==='completed' && ($match['a_score']!==null || $match['b_score']!==null)): ?>
<div class="score-display">
<div class="meta-label mb-2">Final Score</div>
<div class="score"><?= (int)$match['a_score'] ?> <span class="text-muted mx-2">-</span> <?= (int)$match['b_score'] ?></div>
</div>
<?php endif; ?>
<div class="match-meta">
<div class="meta-item"><i class="bi bi-calendar3"></i><div><div class="meta-label">Date</div><div class="meta-value"><?= date('M d, Y', strtotime($match['date'])) ?></div></div></div>
<div class="meta-item"><i class="bi bi-clock"></i><div><div class="meta-label">Time</div><div class="meta-value"><?= date('h:i A', strtotime($match['time'])) ?></div></div></div>
<?php if (!empty($match['venue'])): ?>
<div class="meta-item"><i class="bi bi-geo-alt"></i><div><div class="meta-label">Venue</div><div class="meta-value"><?= htmlspecialchars($match['venue']) ?></div></div></div>
<?php endif; ?>
<div class="meta-item"><i class="bi bi-<?= $isLive ? 'broadcast' : 'info-circle' ?>"></i><div><div class="meta-label">Status</div><div class="meta-value"><?php if ($isLive): ?><span class="text-danger fw-bold">LIVE</span><?php else: ?><?= htmlspecialchars($match['status']) ?><?php endif; ?></div></div></div>
</div>
</div>
<div class="footer-note">
<p class="mb-1"><i class="bi bi-<?= livestreamPlatformIcon($parsed['platform']) ?> me-1"></i>Stream powered by <?= livestreamPlatformLabel($parsed['platform']) ?></p>
<p class="mb-0 small">&copy; <?= date('Y') ?> League Management System</p>
</div>
<script src="assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>

