<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
requireRole([3]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();

// Fetch archived leagues with organizer info
$archived = $conn->query("SELECT l.id, l.league_name, l.sport, l.max_team, l.start_date, l.end_date, l.stat, l.archived_at, l.created_by, CONCAT(u.fname, ' ', u.lname) AS organizer_name FROM tbl_league l LEFT JOIN tbl_user u ON u.id = l.created_by WHERE l.is_archived=1 ORDER BY l.archived_at DESC");
$archivedRows = [];
if ($archived) while ($r = $archived->fetch_assoc()) $archivedRows[] = $r;
$sports = array_values(array_filter(array_unique(array_column($archivedRows, 'sport')), static fn($sport) => trim((string)$sport) !== ''));
sort($sports, SORT_NATURAL | SORT_FLAG_CASE);
$organizerCount = count(array_unique(array_filter(array_column($archivedRows, 'created_by'))));
$completedCount = count(array_filter($archivedRows, static fn($row) => in_array(strtolower($row['stat'] ?? ''), ['completed', 'done'], true)));

include 'includes/header.php';
?>
<?php include 'includes/users-styles.php'; ?>
<?php include 'includes/archive-styles.php'; ?>
<body id="page-top" class="body admin-users admin-archive">
<?php include 'includes/bar.php'; ?>
<div class="container-fluid" style="padding-top:20px;">
<div class="row p-3">
<div class="card cards users-shell">
<div class="card-body">
    
    
    <div class="section-head">
        <div><span class="users-eyebrow">League history</span><h2 class="section-title">Archived leagues</h2></div>
        <span class="section-meta" id="archiveResultCount" aria-live="polite"><?= count($archivedRows) ?> records</span>
    </div>
    <div class="users-filter-bar">
        <div class="filter-group">
            <select id="sportFilter" class="form-select" aria-label="Filter by sport" onchange="filterTable()">
                <option value="all">All sports</option>
                <?php foreach($sports as $sp): ?>
                <option value="<?= htmlspecialchars(strtolower($sp)) ?>"><?= htmlspecialchars(ucfirst($sp)) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="search-wrap"><i class="bi bi-search search-icon"></i><input type="search" id="archiveSearch" class="form-control" aria-label="Search archived leagues or organizers" placeholder="Search leagues or organizers..." oninput="filterTable()"></div>
        </div>
        <button type="button" class="users-clear" onclick="resetArchiveFilters()"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset filters</button>
    </div>
    <div id="archiveNoResults" class="users-no-results" hidden>No archived leagues match these filters. Try another search or reset the filters.</div>

    <?php if (count($archivedRows) > 0): ?>
    <div class="archive-panel"><div class="archive-panel-heading"><span>League records</span><small>Download individual league reports as CSV or PDF</small></div><div class="table-responsive">
        <table class="table users-table align-middle" id="archiveTable">
            <thead>
                <tr><th>#</th><th>League Name</th><th>Sport</th><th>Organizer</th><th>Teams</th><th>Start</th><th>End</th><th>Archived At</th><th>Status</th><th>Export</th></tr>
            </thead>
            <tbody>
            <?php foreach ($archivedRows as $i => $row):
                $statColor = match(strtolower($row['stat'] ?? '')) {
                    'completed','done' => 'success', 'ongoing','active' => 'primary',
                    'upcoming' => 'warning', default => 'secondary'
                };
            ?>
            <tr data-sport="<?= htmlspecialchars(strtolower($row['sport'])) ?>" data-name="<?= htmlspecialchars(strtolower($row['league_name'])) ?>" data-organizer="<?= htmlspecialchars(strtolower($row['organizer_name'] ?? '')) ?>">
                <td><?= $i+1 ?></td>
                <td class="archive-league-name"><?= htmlspecialchars($row['league_name']) ?></td>
                <td><?= htmlspecialchars(ucfirst($row['sport'])) ?></td>
                <td><?= htmlspecialchars($row['organizer_name'] ?? 'Unknown') ?></td>
                <td class="text-center"><?= intval($row['max_team']) ?></td>
                <td><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
                <td><?= date('M d, Y', strtotime($row['end_date'])) ?></td>
                <td style="font-size:12px;"><?= $row['archived_at'] ? date('M d, Y', strtotime($row['archived_at'])) : '—' ?></td>
                <td><span class="archive-status is-<?= $statColor ?>"><?= htmlspecialchars($row['stat'] ?? 'Archived') ?></span></td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="action/archiveExport.php?league_id=<?= $row['id'] ?>&format=csv" class="archive-export is-csv" title="Export CSV">
                            <i class="bi bi-filetype-csv me-1"></i><span style="font-size:11px;">CSV</span>
                        </a>
                        <a href="action/archiveExport.php?league_id=<?= $row['id'] ?>&format=pdf" class="archive-export is-pdf" title="Export PDF" target="_blank" rel="noopener">
                            <i class="bi bi-filetype-pdf me-1"></i><span style="font-size:11px;">PDF</span>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <?php else: ?>
    <div class="archive-empty">
        <i class="bi bi-archive" style="font-size:3rem;opacity:0.4;display:block;margin-bottom:12px;"></i>
        <h4>No archived leagues yet</h4><p>Leagues will appear here once they have been archived.</p>
    </div>
    <?php endif; ?>
</div>
</div>
</div>
</div>

<script>
function filterTable() {
    const sport = document.getElementById('sportFilter').value;
    const q = document.getElementById('archiveSearch').value.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('#archiveTable tbody tr').forEach(row => {
        const ms = (sport === 'all' || row.dataset.sport === sport);
        const mq = row.dataset.name.includes(q) || row.dataset.organizer.includes(q);
        row.style.display = (ms && mq) ? '' : 'none';
        if (ms && mq) visible++;
    });
    document.getElementById('archiveResultCount').textContent = visible + ' record' + (visible !== 1 ? 's' : '');
    document.getElementById('archiveNoResults').hidden = visible !== 0 || !document.getElementById('archiveTable');
}
function resetArchiveFilters() {
    document.getElementById('sportFilter').value = 'all';
    document.getElementById('archiveSearch').value = '';
    filterTable();
}
</script>
<?php include 'includes/footer.php'; ?>
</body></html>
