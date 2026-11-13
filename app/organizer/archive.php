<?php
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
$full = currentUserName();
$userId = currentUserId();
requireRole([1]);
requireActiveAccount($conn);

// Fetch archived leagues owned by this organizer within 30 days
$stmt = $conn->prepare("SELECT id, league_name, sport, division, category, max_team, start_date, end_date, archived_at, DATEDIFF(DATE_ADD(archived_at, INTERVAL 30 DAY), CURDATE()) AS days_remaining FROM tbl_league WHERE created_by = ? AND is_archived = 1 AND archived_at IS NOT NULL AND archived_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY archived_at DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$archivedLeagues = [];
while ($row = $result->fetch_assoc()) {
    $archivedLeagues[] = $row;
}
$stmt->close();

include 'includes/header.php';
?>

<body id="page-top" class="body organizer-archive">
    <?php include 'includes/bar.php'; ?>
    <?php include 'includes/dashboard-styles.php'; ?>

    <?php include 'includes/archive-list-styles.php'; ?>

    <div class="container-fluid" style="padding-top: 20px; min-height: 800px;">
        <div class="row p-3">
            <div class="card cards" style="background:transparent;box-shadow:none;border:none;">
                <div class="card-body p-0">

                    

                    
                    <div class="activity-section dash-animate dash-animate-d2 mb-4">
                        <div class="activity-header">
                            <div>
                                <span class="section-eyebrow">Restore window</span>
                                <h2 class="section-title">Recently archived <span class="activity-badge-count"><?= count($archivedLeagues) ?> total</span></h2>
                            </div>
                            <?php if (!empty($archivedLeagues)): ?>
                                <input type="text" class="archive-search-input" id="archiveSearch" aria-label="Search archived leagues" placeholder="Search archived leagues...">
                            <?php endif; ?>
                        </div>

                        <?php if (empty($archivedLeagues)): ?>
                            <div class="archive-empty">
                                <div class="archive-empty-icon">
                                    <i class="bi bi-archive"></i>
                                </div>
                                <div class="archive-empty-title">No archived leagues</div><p class="archive-empty-text">Leagues you archive will appear here during their 30-day restore window.</p><a class="archive-empty-link" href="league.php">View your leagues <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                
                            </div>
                        <?php else: ?>
                            <div class="league-table-wrapper table-responsive">
                                <table id="archiveTable" class="table activity-table mb-0">
                                    <thead>
                                        <tr>
                                            <th><i class="bi bi-hash me-1"></i>#</th>
                                            <th><i class="bi bi-trophy me-1"></i>League Name</th>
                                            <th><i class="bi bi-dribbble me-1"></i>Sport</th>
                                            <th><i class="bi bi-diagram-3 me-1"></i>Division</th>
                                            <th><i class="bi bi-tag me-1"></i>Category</th>
                                            <th><i class="bi bi-people me-1"></i>Max Teams</th>
                                            <th><i class="bi bi-calendar-event me-1"></i>Start Date</th>
                                            <th><i class="bi bi-calendar-check me-1"></i>End Date</th>
                                            <th><i class="bi bi-archive me-1"></i>Archived On</th>
                                            <th><i class="bi bi-clock-history me-1"></i>Days Left</th>
                                            <th><i class="bi bi-gear me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($archivedLeagues as $i => $league): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><strong><?= htmlspecialchars($league['league_name']) ?></strong></td>
                                                <td><?= htmlspecialchars($league['sport']) ?></td>
                                                <td><?= htmlspecialchars($league['division']) ?></td>
                                                <td><?= htmlspecialchars($league['category']) ?></td>
                                                <td><?= htmlspecialchars($league['max_team']) ?></td>
                                                <td><?= date('M d, Y', strtotime($league['start_date'])) ?></td>
                                                <td><?= date('M d, Y', strtotime($league['end_date'])) ?></td>
                                                <td><?= date('M d, Y', strtotime($league['archived_at'])) ?></td>
                                                <td>
                                                    <?php if ($league['days_remaining'] > 0): ?>
                                                        <span class="archive-badge-warning"><?= $league['days_remaining'] ?> days</span>
                                                    <?php else: ?>
                                                        <span class="archive-badge-danger">Expired</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="archive-restore-btn restore-btn" 
                                                            data-league-id="<?= $league['id'] ?>" 
                                                            data-league-name="<?= htmlspecialchars($league['league_name']) ?>">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Restore
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Search filter for archive table
        var searchInput = document.getElementById('archiveSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var q = this.value.toLowerCase();
                var table = document.getElementById('archiveTable');
                if (!table) return;
                table.querySelectorAll('tbody tr').forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    row.style.display = text.indexOf(q) !== -1 ? '' : 'none';
                });
            });
        }

        document.querySelectorAll('.restore-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const leagueId = this.dataset.leagueId;
                const leagueName = this.dataset.leagueName;
                
                Swal.fire({
                    title: 'Restore League?',
                    html: `Are you sure you want to restore <strong>${leagueName}</strong>?<br><br>The league will become active again.`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-arrow-counterclockwise"></i> Yes, Restore',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('restoreLeague', 'true');
                        formData.append('league_id', leagueId);
                        
                        fetch('action/leagueAction.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 201) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Restored!',
                                    text: data.msg,
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', data.msg || 'Failed to restore league.', 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error', 'An error occurred while restoring the league.', 'error');
                        });
                    }
                });
            });
        });
    });
    </script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
