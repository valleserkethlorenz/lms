<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/summaryStrip.php';
require_once __DIR__ . '/../../includes/rosterSize.php';
requireRole([3]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();

$msg = '';
$allowedCategories = ['sport','division','venue','document_type','category'];

// Generate CSRF token if not exists
if (empty($_SESSION['admin_utility_csrf_token'])) {
    $_SESSION['admin_utility_csrf_token'] = bin2hex(random_bytes(32));
}

// Category metadata for UI
$categoryMeta = [
    'sport'         => ['icon' => 'bi-trophy-fill',        'label' => 'Sports',         'color' => '#09173c', 'bg' => '#e8edf7'],
    'division'      => ['icon' => 'bi-diagram-3-fill',     'label' => 'Divisions',      'color' => '#2563eb', 'bg' => '#dbeafe'],
    'venue'         => ['icon' => 'bi-geo-alt-fill',       'label' => 'Venues',         'color' => '#0e4d8a', 'bg' => '#cfe8f7'],
    'document_type' => ['icon' => 'bi-file-earmark-text-fill','label' => 'Document Types','color' => '#1e6fa8','bg' => '#d0edfb'],
    'category'      => ['icon' => 'bi-tags-fill',          'label' => 'Categories',     'color' => '#6f42c1', 'bg' => '#ede7f6'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['admin_utility_csrf_token'], $csrf)) {
        $msg = '<div class="alert alert-danger">The form session expired. Refresh the page and try again.</div>';
    } else {
    // AJAX: check how many active leagues use the old name before allowing rename
    if (isset($_POST['checkRenameUtility'])) {
        $id = intval($_POST['uid']);
        $newName = trim($_POST['new_name'] ?? '');
        $stmt = $conn->prepare("SELECT name, category FROM tbl_utility WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $count = 0;
        $colLabel = '';
        if ($row && $row['name'] !== $newName) {
            $leagueColMap = [
                'sport'         => 'sport',
                'division'      => 'division',
                'venue'         => 'venue',
                'category'      => 'category',
            ];
            if (isset($leagueColMap[$row['category']])) {
                $col = $leagueColMap[$row['category']];
                $colLabel = $col;
                $cStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM tbl_league WHERE `$col`=? AND (is_archived=0 OR is_archived IS NULL)");
                $cStmt->bind_param('s', $row['name']);
                $cStmt->execute();
                $count = (int)$cStmt->get_result()->fetch_assoc()['cnt'];
                $cStmt->close();
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['count' => $count, 'oldName' => $row['name'] ?? '', 'newName' => $newName, 'column' => $colLabel]);
        exit;
    }
    if (isset($_POST['addUtility'])) {
        $cat  = $_POST['category'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $minAge = ($_POST['min_age'] ?? '') === '' ? null : intval($_POST['min_age']);
        $maxAge = ($_POST['max_age'] ?? '') === '' ? null : intval($_POST['max_age']);
        $defaultMinPlayers = ($_POST['default_min_players'] ?? '') === '' ? null : intval($_POST['default_min_players']);
        $defaultMaxPlayers = ($_POST['default_max_players'] ?? '') === '' ? null : intval($_POST['default_max_players']);
        $appliesTo = in_array($_POST['applies_to'] ?? '', ['team','player']) ? $_POST['applies_to'] : null;
        if (!in_array($cat, $allowedCategories) || !$name) {
            $msg = '<div class="alert alert-danger">Category and name are required.</div>';
        } elseif ($cat === 'division' && ($minAge === null || $maxAge === null || $minAge < 0 || $maxAge > 100 || $minAge > $maxAge)) {
            $msg = '<div class="alert alert-danger">Division ages must be from 0 to 100, and minimum age cannot exceed maximum age.</div>';
        } elseif ($cat === 'sport' && ($defaultMinPlayers === null || $defaultMaxPlayers === null || $defaultMinPlayers < 1 || $defaultMaxPlayers > 100 || $defaultMinPlayers > $defaultMaxPlayers)) {
            $msg = '<div class="alert alert-danger">Sport roster defaults must be from 1 to 100, and the minimum cannot exceed the maximum.</div>';
        } elseif ($cat === 'document_type' && !$appliesTo) {
            $msg = '<div class="alert alert-danger">Document scope (per team or per player) is required for document types.</div>';
        } else {
            $minAge = $cat === 'division' ? $minAge : null;
            $maxAge = $cat === 'division' ? $maxAge : null;
            $defaultMinPlayers = $cat === 'sport' ? $defaultMinPlayers : null;
            $defaultMaxPlayers = $cat === 'sport' ? $defaultMaxPlayers : null;
            $appliesTo = $cat === 'document_type' ? $appliesTo : null;
            $stmt = $conn->prepare("INSERT INTO tbl_utility (category,name,min_age,max_age,default_min_players,default_max_players,applies_to,is_active,created_by) VALUES (?,?,?,?,?,?,?,1,?)");
            $stmt->bind_param('ssiiiisi', $cat, $name, $minAge, $maxAge, $defaultMinPlayers, $defaultMaxPlayers, $appliesTo, $userId);
            if ($stmt->execute()) {
                auditLog($conn, 'Add Utility', "Added utility: [$cat] $name");
                $msg = '<div class="alert alert-success">Utility record added.</div>';
            } else if ($stmt->errno === 1062) {
                $msg = '<div class="alert alert-danger">That utility record already exists.</div>';
            } else {
                $msg = '<div class="alert alert-danger">Unable to add utility record.</div>';
            }
            $stmt->close();
        }
    }
    if (isset($_POST['editUtility'])) {
        $id   = intval($_POST['uid']);
        $name = trim($_POST['name'] ?? '');
        $rowStmt=$conn->prepare("SELECT category FROM tbl_utility WHERE id=?"); $rowStmt->bind_param('i',$id); $rowStmt->execute(); $current=$rowStmt->get_result()->fetch_assoc(); $rowStmt->close();
        $cat=$current['category'] ?? '';
        $minAge = ($_POST['min_age'] ?? '') === '' ? null : intval($_POST['min_age']);
        $maxAge = ($_POST['max_age'] ?? '') === '' ? null : intval($_POST['max_age']);
        $defaultMinPlayers = ($_POST['default_min_players'] ?? '') === '' ? null : intval($_POST['default_min_players']);
        $defaultMaxPlayers = ($_POST['default_max_players'] ?? '') === '' ? null : intval($_POST['default_max_players']);
        $appliesTo = in_array($_POST['applies_to'] ?? '', ['team','player']) ? $_POST['applies_to'] : null;
        if (!$name) {
            $msg = '<div class="alert alert-danger">Name is required.</div>';
        } elseif ($cat === 'division' && ($minAge === null || $maxAge === null || $minAge < 0 || $maxAge > 100 || $minAge > $maxAge)) {
            $msg = '<div class="alert alert-danger">Enter a valid age bracket from 0 to 100.</div>';
        } elseif ($cat === 'sport' && ($defaultMinPlayers === null || $defaultMaxPlayers === null || $defaultMinPlayers < 1 || $defaultMaxPlayers > 100 || $defaultMinPlayers > $defaultMaxPlayers)) {
            $msg = '<div class="alert alert-danger">Enter valid sport roster defaults from 1 to 100.</div>';
        } elseif ($cat === 'document_type' && !$appliesTo) {
            $msg = '<div class="alert alert-danger">Document scope (per team or per player) is required for document types.</div>';
        } else {
            $minAge = $cat === 'division' ? $minAge : null; $maxAge = $cat === 'division' ? $maxAge : null;
            $defaultMinPlayers = $cat === 'sport' ? $defaultMinPlayers : null;
            $defaultMaxPlayers = $cat === 'sport' ? $defaultMaxPlayers : null;
            $appliesTo = $cat === 'document_type' ? $appliesTo : null;
            $stmt = $conn->prepare("UPDATE tbl_utility SET name=?,min_age=?,max_age=?,default_min_players=?,default_max_players=?,applies_to=? WHERE id=?");
            $stmt->bind_param('siiiisi', $name, $minAge, $maxAge, $defaultMinPlayers, $defaultMaxPlayers, $appliesTo, $id);
            if ($stmt->execute()) {
                auditLog($conn, 'Edit Utility', "Edited utility ID $id: $name");
                $msg = '<div class="alert alert-success">Utility record updated.</div>';
            } else if ($stmt->errno === 1062) {
                $msg = '<div class="alert alert-danger">That utility name already exists in this category.</div>';
            }
            $stmt->close();
        }
    }
    if (isset($_POST['toggleUtility'])) {
        $id      = intval($_POST['uid']);
        $newStat = intval($_POST['new_stat']);
        $stmt = $conn->prepare("UPDATE tbl_utility SET is_active=? WHERE id=?");
        $stmt->bind_param('ii', $newStat, $id);
        if ($stmt->execute()) {
            $action = $newStat ? 'Activate Utility' : 'Deactivate Utility';
            auditLog($conn, $action, "Set is_active=$newStat for utility ID $id");
            $msg = '<div class="alert alert-success">Status updated.</div>';
        }
        $stmt->close();
    }
    if (isset($_POST['deleteUtility'])) {
        $id = intval($_POST['uid']);
        // Fetch the utility record to run validation checks
        $fetchStmt = $conn->prepare("SELECT category, name, is_active FROM tbl_utility WHERE id=?");
        $fetchStmt->bind_param('i', $id);
        $fetchStmt->execute();
        $util = $fetchStmt->get_result()->fetch_assoc();
        $fetchStmt->close();
        if (!$util) {
            $msg = '<div class="alert alert-danger">Utility record not found.</div>';
        }
        // Layer 1: Block deletion of active utilities — must deactivate first
        elseif ($util['is_active'] == 1) {
            $msg = '<div class="alert alert-danger">Cannot delete an active utility. Please deactivate it first, then try deleting again.</div>';
        } else {
            // Layer 2: Block deletion if any league references this utility by name
            $leagueColMap = [
                'sport'         => ['col' => 'sport',    'match' => $util['name']],
                'division'      => ['col' => 'division', 'match' => $util['name']],
                'venue'         => ['col' => 'venue',    'match' => $util['name']],
                'category'      => ['col' => 'category', 'match' => $util['name']],
            ];
            $leagueRefCount = 0;
            $leagueColLabel = '';
            if (isset($leagueColMap[$util['category']])) {
                $lm = $leagueColMap[$util['category']];
                $leagueColLabel = $lm['col'];
                $lStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM tbl_league WHERE `".$lm['col']."`=? AND (is_archived=0 OR is_archived IS NULL)");
                $lStmt->bind_param('s', $lm['match']);
                $lStmt->execute();
                $leagueRefCount = $lStmt->get_result()->fetch_assoc()['cnt'];
                $lStmt->close();
            }
            if ($leagueRefCount > 0) {
                $msg = '<div class="alert alert-danger">Cannot delete this utility because '.$leagueRefCount.' league(s) still use "'.htmlspecialchars($util['name']).'" as their '.$leagueColLabel.'. Remove or update those leagues first.</div>';
            } else {
                // Layer 3: Block deletion if document requirements reference this utility (FK)
                $docRefCount = 0;
                $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM tbl_league_document_requirement WHERE document_type_id=?");
                $checkStmt->bind_param('i', $id);
                $checkStmt->execute();
                $docRefCount = $checkStmt->get_result()->fetch_assoc()['cnt'];
                $checkStmt->close();
                if ($docRefCount > 0) {
                    $msg = '<div class="alert alert-danger">Cannot delete this utility because it is referenced by '.$docRefCount.' document requirement(s). Remove those references first.</div>';
                } else {
                    // All checks passed — proceed with deletion
                    $stmt = $conn->prepare("DELETE FROM tbl_utility WHERE id=?");
                    $stmt->bind_param('i', $id);
                    if ($stmt->execute()) {
                        auditLog($conn, 'Delete Utility', "Deleted utility ID $id: [".$util['category']."] ".$util['name']);
                        $msg = '<div class="alert alert-success">Utility record deleted.</div>';
                    } else {
                        $msg = '<div class="alert alert-danger">Unable to delete utility record.</div>';
                    }
                    $stmt->close();
                }
            }
        }
    }
    } // end CSRF else
} // end POST

// Fetch all utility records grouped by category
$utilities = [];
$res = $conn->query("SELECT * FROM tbl_utility ORDER BY category, name");
if ($res) while ($r = $res->fetch_assoc()) $utilities[$r['category']][] = $r;

// Refresh summary counts after any record changes
$categoryCounts = [];
$countResult = $conn->query("SELECT category, COUNT(*) as cnt FROM tbl_utility GROUP BY category");
if ($countResult) {
    while ($row = $countResult->fetch_assoc()) {
        $categoryCounts[$row['category']] = (int)$row['cnt'];
    }
}
$totalUtilities = array_sum($categoryCounts);


$catLabels = ['sport'=>'Sports','division'=>'Age Brackets','venue'=>'Venues','document_type'=>'Document Types','category'=>'Categories'];

include 'includes/header.php';
?>
<?php include 'includes/users-styles.php'; ?>
<?php include 'includes/utility-styles.php'; ?>
<?php include_once __DIR__ . '/../../includes/summary-strip-styles.php'; ?>
<body id="page-top" class="body admin-users admin-utility">
<?php include 'includes/bar.php'; ?>
<div class="container-fluid" style="padding-top:20px;">
<div class="row p-3">
<div class="card cards users-shell">
<div class="card-body">
    
    <div role="status" data-lms-toast-source><?= $msg ?></div>


    <?php
    $utilitySummaryNotes = [
        'sport'         => 'Roster defaults',
        'division'      => 'Age brackets',
        'venue'         => 'Match locations',
        'document_type' => 'Submission requirements',
        'category'      => 'League classifications',
    ];
    $utilitySummaryCards = [];
    foreach ($categoryMeta as $catKey => $cm) {
        $utilitySummaryCards[] = [
            'label' => $cm['label'],
            'value' => $categoryCounts[$catKey] ?? 0,
            'note'  => $utilitySummaryNotes[$catKey] ?? '',
        ];
    }
    renderSummaryStrip($utilitySummaryCards, 'Reference data totals');
    ?>
    <div class="section-head">
        <div><span class="users-eyebrow">Reference library</span><h2 class="section-title">Manage records</h2></div>
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="page-header-btn utility-add-record-btn" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle-fill" aria-hidden="true"></i>Add Record
            </button>
            
        </div>
    </div>
    <!-- Tabs -->
    <div class="util-tabs" id="utilTabs">
        <button type="button" class="util-tab active" aria-pressed="true" data-cat="all" onclick="filterByTab('all')"><i class="bi bi-grid-3x3-gap-fill"></i> All</button>
        <?php foreach($categoryMeta as $catKey=>$cm): ?><button type="button" class="util-tab" aria-pressed="false" data-cat="<?= $catKey ?>" onclick="filterByTab('<?= $catKey ?>')"><i class="bi <?= $cm['icon'] ?>"></i> <?= $cm['label'] ?></button><?php endforeach; ?>
    </div>
    <!-- Table -->
    <div class="util-table-wrap">
        <div class="utility-toolbar">
            <div class="util-search-wrap"><i class="bi bi-search"></i><input type="text" id="utilSearch" aria-label="Search utility records" placeholder="Search records..."></div>
            <div class="text-muted" style="font-size:12px" id="utilResultCount" aria-live="polite"></div>
        </div>
        <div id="utilNoResults" class="users-no-results" hidden>No records match your search in this category.</div>
        <div class="table-responsive"><table class="table util-table mb-0" id="utilTable">
            <thead><tr><th style="width:50px">#</th><th>Name</th><th>Category</th><th>Details</th><th>Status</th><th style="width:100px;text-align:center">Actions</th></tr></thead>
            <tbody>
            <?php $rowNum=0;$flatUtil=[];$res2=$conn->query("SELECT * FROM tbl_utility ORDER BY category,name");if($res2)while($r=$res2->fetch_assoc())$flatUtil[]=$r;
            if(empty($flatUtil)):?><tr><td colspan="6"><div class="util-empty-state"><i class="bi bi-inbox"></i><div style="font-size:15px;font-weight:500;color:#6b7280">No utility records found</div><div style="font-size:13px">Click "Add Record" to create your first entry.</div></div></td></tr>
            <?php else:foreach($flatUtil as $u):$rowNum++;
            $cm=$categoryMeta[$u['category']]??['icon'=>'bi-tag-fill','label'=>$u['category'],'color'=>'#495057','bg'=>'#f0f0f0'];
            $ei='';if($u['category']==='division'&&$u['min_age']!==null&&$u['max_age']!==null)$ei='<span class="info-chip"><i class="bi bi-calendar3"></i> Ages '.$u['min_age'].'–'.$u['max_age'].'</span>';
            if($u['category']==='sport'&&($u['default_min_players']!==null||$u['default_max_players']!==null)){$rosterChip=($u['default_min_players']!==null&&$u['default_max_players']!==null)?rosterSizeLabel($u['default_min_players'],$u['default_max_players']):(($u['default_min_players']??'?').'–'.($u['default_max_players']??'?').' players');$ei='<span class="info-chip"><i class="bi bi-people-fill"></i> '.htmlspecialchars($rosterChip,ENT_QUOTES).'</span>';}
            if($u['category']==='document_type'&&$u['applies_to']!==null)$ei='<span class="info-chip" style="background:'.($u['applies_to']==='team'?'#fef3c7':'#dbeafe').';color:'.($u['applies_to']==='team'?'#92400e':'#1e40af').'"><i class="bi '.($u['applies_to']==='team'?'bi-people-fill':'bi-person-fill').'"></i> '.($u['applies_to']==='team'?'Per Team':'Per Player').'</span>';
            if(empty($ei))$ei='<span style="color:#9ca3af">—</span>';
            $ed=htmlspecialchars(json_encode([$u['id'],$u['name'],$u['category'],$u['min_age'],$u['max_age'],$u['default_min_players'],$u['default_max_players'],$u['applies_to']]),ENT_QUOTES);?>
            <tr data-category="<?= $u['category'] ?>"><td style="color:#9ca3af;font-size:13px"><?= $rowNum ?></td><td><span style="font-weight:500;color:#09173c"><?= htmlspecialchars($u['name']) ?></span></td><td><span class="cat-badge"><i class="bi <?= $cm['icon'] ?>"></i> <?= $cm['label'] ?></span></td><td><?= $ei ?></td>
            <td><?php if($u['is_active']):?><span class="status-badge active"><span class="status-dot"></span> Active</span><?php else:?><span class="status-badge inactive"><span class="status-dot"></span> Inactive</span><?php endif;?></td>
            <td style="text-align:center;white-space:nowrap"><button class="util-action-btn edit" title="Edit" onclick='openEdit(...<?= $ed ?>)'><i class="bi bi-pencil-square"></i></button> <?php if($u['is_active']):?><button class="util-action-btn toggle-off" title="Deactivate" onclick="toggleUtil(<?= $u['id'] ?>,0)"><i class="bi bi-toggle-on"></i></button><?php else:?><button class="util-action-btn toggle-on" title="Activate" onclick="toggleUtil(<?= $u['id'] ?>,1)"><i class="bi bi-toggle-off"></i></button><?php endif;?></td></tr>
            <?php endforeach;endif;?>
            </tbody></table>
        </div>
    </div>
</div>
</div>
</div>
</div>

<!-- Add Modal -->
<div class="modal fade util-modal" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:540px">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_utility_csrf_token']) ?>">
                <div class="modal-header">
                    <div><h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i>Add Utility Record</h5><small style="opacity:.7;font-size:12px">Create a new reference entry</small></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="util-form-section" style="border-top-color:#09173c">
                        <div class="util-form-section-header">
                            <div class="util-form-section-icon" style="background:#09173c"><i class="bi bi-info-circle-fill"></i></div>
                            <div><h6 class="util-form-section-title">Basic Information</h6><span class="util-form-section-sub">Select category and enter name</span></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Category <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text" style="background:#f0f4ff;border-color:#dee2e6"><i class="bi bi-tag-fill" style="color:#09173c;font-size:14px"></i></span>
                                <select name="category" id="addUtilityCategory" class="form-select" required onchange="toggleUtilityFields('add')"><?php foreach($allowedCategories as $c):?><option value="<?= $c ?>"><?= $categoryMeta[$c]['label'] ?></option><?php endforeach;?></select></div></div>
                            <div class="col-md-6"><label class="form-label">Name <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text" style="background:#f0f4ff;border-color:#dee2e6"><i class="bi bi-pencil" style="color:#09173c;font-size:14px"></i></span><input type="text" name="name" class="form-control" required placeholder=""></div></div>
                        </div>
                    </div>
                    <div id="addAgeFields" class="util-form-section d-none" style="border-top-color:#2563eb">
                        <div class="util-form-section-header"><div class="util-form-section-icon" style="background:#2563eb"><i class="bi bi-calendar3"></i></div><div><h6 class="util-form-section-title">Age Range</h6><span class="util-form-section-sub">Min and max age for this division</span></div></div>
                        <div class="row g-3"><div class="col-6"><label class="form-label">Minimum Age <span class="text-danger">*</span></label><input type="number" min="0" max="100" name="min_age" id="addMinAge" class="form-control" placeholder="0"></div><div class="col-6"><label class="form-label">Maximum Age <span class="text-danger">*</span></label><input type="number" min="0" max="100" name="max_age" id="addMaxAge" class="form-control" placeholder="100"></div></div>
                    </div>
                    <div id="addSportFields" class="util-form-section d-none" style="border-top-color:#0e4d8a">
                        <div class="util-form-section-header"><div class="util-form-section-icon" style="background:#0e4d8a"><i class="bi bi-people-fill"></i></div><div><h6 class="util-form-section-title">Default Roster Size</h6><span class="util-form-section-sub">Prefill new league forms with these limits</span></div></div>
                        <div class="row g-3"><div class="col-6"><label class="form-label">Min Players <span class="text-danger">*</span></label><input type="number" min="1" max="100" name="default_min_players" id="addDefaultMinPlayers" class="form-control" placeholder="5"></div><div class="col-6"><label class="form-label">Max Players <span class="text-danger">*</span></label><input type="number" min="1" max="100" name="default_max_players" id="addDefaultMaxPlayers" class="form-control" placeholder="15"></div></div>
                    </div>
                    <div id="addDocScopeFields" class="util-form-section d-none" style="border-top-color:#1e6fa8">
                        <div class="util-form-section-header"><div class="util-form-section-icon" style="background:#1e6fa8"><i class="bi bi-file-earmark-text-fill"></i></div><div><h6 class="util-form-section-title">Document Scope</h6><span class="util-form-section-sub">Who must submit this document</span></div></div>
                        <div class="row g-3"><div class="col-12"><label class="form-label">Applies To <span class="text-danger">*</span></label><select name="applies_to" id="addAppliesTo" class="form-select"><option value="" disabled selected>Select scope</option><option value="player">Per Player — each player uploads individually</option><option value="team">Per Team — one upload covers the whole team</option></select></div></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:4px">Cancel</button>
                    <button type="submit" name="addUtility" class="btn" style="background:#09173c;color:#fff;border-radius:4px;padding:8px 24px"><i class="bi bi-check-circle-fill me-1"></i>Add Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade util-modal" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:540px">
        <div class="modal-content">
            <form method="POST" id="editForm" onsubmit="return handleEditSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_utility_csrf_token']) ?>">
                <input type="hidden" name="uid" id="editUid">
                <input type="hidden" id="editCategory" name="category">
                <div class="modal-header" style="background:#09173c">
                    <div><h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Utility Record</h5><small style="opacity:.7;font-size:12px">Update the details of this entry</small></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="util-form-section" style="border-top-color:#d97706">
                        <div class="util-form-section-header"><div class="util-form-section-icon" style="background:#09173c"><i class="bi bi-info-circle-fill"></i></div><div><h6 class="util-form-section-title">Basic Information</h6><span class="util-form-section-sub">Category is shown read-only; update the name</span></div></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Category</label><div class="input-group"><span class="input-group-text" style="background:#f0f4ff;border-color:#dee2e6"><i class="bi bi-lock-fill" style="color:#9ca3af;font-size:14px"></i></span><input type="text" id="editCategoryDisplay" class="form-control" disabled style="background:#f8f9fa"></div></div>
                            <div class="col-md-6"><label class="form-label">Name <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text" style="background:#f0f4ff;border-color:#dee2e6"><i class="bi bi-pencil" style="color:#09173c;font-size:14px"></i></span><input type="text" name="name" id="editName" class="form-control" required></div></div>
                        </div>
                    </div>
                    <div id="editAgeFields" class="util-form-section d-none" style="border-top-color:#2563eb">
                        <div class="util-form-section-header"><div class="util-form-section-icon" style="background:#2563eb"><i class="bi bi-calendar3"></i></div><div><h6 class="util-form-section-title">Age Range</h6><span class="util-form-section-sub">Min and max age for this division</span></div></div>
                        <div class="row g-3"><div class="col-6"><label class="form-label">Minimum Age <span class="text-danger">*</span></label><input type="number" min="0" max="100" name="min_age" id="editMinAge" class="form-control"></div><div class="col-6"><label class="form-label">Maximum Age <span class="text-danger">*</span></label><input type="number" min="0" max="100" name="max_age" id="editMaxAge" class="form-control"></div></div>
                    </div>
                    <div id="editSportFields" class="util-form-section d-none" style="border-top-color:#0e4d8a">
                        <div class="util-form-section-header"><div class="util-form-section-icon" style="background:#0e4d8a"><i class="bi bi-people-fill"></i></div><div><h6 class="util-form-section-title">Default Roster Size</h6><span class="util-form-section-sub">Prefill new league forms with these limits</span></div></div>
                        <div class="row g-3"><div class="col-6"><label class="form-label">Min Players <span class="text-danger">*</span></label><input type="number" min="1" max="100" name="default_min_players" id="editDefaultMinPlayers" class="form-control"></div><div class="col-6"><label class="form-label">Max Players <span class="text-danger">*</span></label><input type="number" min="1" max="100" name="default_max_players" id="editDefaultMaxPlayers" class="form-control"></div></div>
                    </div>
                    <div id="editDocScopeFields" class="util-form-section d-none" style="border-top-color:#1e6fa8">
                        <div class="util-form-section-header"><div class="util-form-section-icon" style="background:#1e6fa8"><i class="bi bi-file-earmark-text-fill"></i></div><div><h6 class="util-form-section-title">Document Scope</h6><span class="util-form-section-sub">Who must submit this document</span></div></div>
                        <div class="row g-3"><div class="col-12"><label class="form-label">Applies To <span class="text-danger">*</span></label><select name="applies_to" id="editAppliesTo" class="form-select"><option value="" disabled>Select scope</option><option value="player">Per Player — each player uploads individually</option><option value="team">Per Team — one upload covers the whole team</option></select></div></div>
                    </div>
                </div>
                <div class="modal-footer" style="justify-content:space-between">
                    <button type="button" class="btn btn-danger" id="editDeleteBtn" onclick="deleteUtil()" style="border-radius:4px;padding:8px 20px"><i class="bi bi-trash-fill me-1"></i>Delete</button>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:4px">Cancel</button>
                        <button type="submit" name="editUtility" class="btn" style="background:#09173c;color:#fff;border-radius:4px;padding:8px 24px"><i class="bi bi-check-circle-fill me-1"></i>Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const catLabels=<?= json_encode(array_combine($allowedCategories,array_map(fn($k)=>$categoryMeta[$k]['label'],$allowedCategories))) ?>;
let currentTab='all';
function filterByTab(cat){
    currentTab=cat;
    document.querySelectorAll('.util-tab').forEach(t=>{const selected=t.dataset.cat===cat;t.classList.toggle('active',selected);t.setAttribute('aria-pressed',String(selected));});
    const sv=(document.getElementById('utilSearch').value||'').toLowerCase();
    let vis=0;
    document.querySelectorAll('#utilTable tbody tr[data-category]').forEach(row=>{
        const mc=(cat==='all'||row.dataset.category===cat);
        const ms=!sv||row.textContent.toLowerCase().includes(sv);
        const show=mc&&ms;row.style.display=show?'':'none';if(show)vis++;
    });
    document.getElementById('utilNoResults').hidden=vis!==0||document.querySelectorAll('#utilTable tbody tr[data-category]').length===0;
    document.getElementById('utilResultCount').textContent=vis+' record'+(vis!==1?'s':'')+' shown';
}
document.getElementById('utilSearch').addEventListener('input',function(){filterByTab(currentTab)});
function toggleUtilityFields(prefix,category){
    const cat=category||document.getElementById(prefix+'UtilityCategory').value;
    document.getElementById(prefix+'AgeFields').classList.toggle('d-none',cat!=='division');
    document.getElementById(prefix+'SportFields').classList.toggle('d-none',cat!=='sport');
    document.getElementById(prefix+'DocScopeFields').classList.toggle('d-none',cat!=='document_type');
    document.querySelectorAll('#'+prefix+'AgeFields input').forEach(i=>i.required=cat==='division');
    document.querySelectorAll('#'+prefix+'SportFields input').forEach(i=>i.required=cat==='sport');
    const scopeSelect=document.getElementById(prefix+'AppliesTo');
    if(scopeSelect)scopeSelect.required=cat==='document_type';
}
let originalEditName='';
function openEdit(id,name,category,minAge,maxAge,defaultMinPlayers,defaultMaxPlayers,appliesTo){
    document.getElementById('editUid').value=id;
    document.getElementById('editName').value=name;
    originalEditName=name;
    document.getElementById('editCategory').value=category;
    document.getElementById('editCategoryDisplay').value=catLabels[category]||category;
    document.getElementById('editMinAge').value=minAge??'';
    document.getElementById('editMaxAge').value=maxAge??'';
    document.getElementById('editDefaultMinPlayers').value=defaultMinPlayers??'';
    document.getElementById('editDefaultMaxPlayers').value=defaultMaxPlayers??'';
    document.getElementById('editAppliesTo').value=appliesTo||'';
    toggleUtilityFields('edit',category);
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function handleEditSubmit(e){
    e.preventDefault();
    const newName=document.getElementById('editName').value.trim();
    if(newName===originalEditName){
        document.getElementById('editForm').submit();
        return false;
    }
    const uid=document.getElementById('editUid').value;
    const btn=document.querySelector('#editForm button[name="editUtility"]');
    if(btn)btn.disabled=true;
    $.post('',{csrf_token:'<?= $_SESSION['admin_utility_csrf_token'] ?>',checkRenameUtility:true,uid:uid,new_name:newName},function(data){
        if(btn)btn.disabled=false;
        if(!data||data.count===0){
            document.getElementById('editForm').submit();
            return;
        }
        Swal.fire({
            title:'Rename this utility?',
            html:`<p style="text-align:left"><strong>${data.count}</strong> active league(s) currently use <strong>"${data.oldName}"</strong> as their <em>${data.column}</em>. They will continue displaying the old name.</p>`,
            icon:'warning',
            showCancelButton:true,
            confirmButtonColor:'#d97706',
            cancelButtonColor:'#6c757d',
            confirmButtonText:'Yes, continue',
            cancelButtonText:'Cancel'
        }).then(r=>{
            if(r.isConfirmed)document.getElementById('editForm').submit();
        });
    },'json');
    return false;
}
function toggleUtil(id,newStat){
    const action=newStat==1?'activate':'deactivate';
    Swal.fire({
        title:`${action.charAt(0).toUpperCase()+action.slice(1)} this utility?`,
        icon:'question',showCancelButton:true,
        confirmButtonColor:'#09173c',cancelButtonColor:'#6c757d',
        confirmButtonText:'Yes, '+action
    }).then(r=>{
        if(!r.isConfirmed)return;
        $.post('',{csrf_token:'<?= $_SESSION['admin_utility_csrf_token'] ?>',toggleUtility:true,uid:id,new_stat:newStat},function(){location.reload()});
    });
}
function deleteUtil(){
    const id=document.getElementById('editUid').value;
    Swal.fire({
        title:'Delete this utility?',
        text:'This action cannot be undone.',
        icon:'warning',showCancelButton:true,
        confirmButtonColor:'#dc3545',cancelButtonColor:'#6c757d',
        confirmButtonText:'Yes, delete it'
    }).then(r=>{
        if(!r.isConfirmed)return;
        $.post('',{csrf_token:'<?= $_SESSION['admin_utility_csrf_token'] ?>',deleteUtility:true,uid:id},function(){location.reload()});
    });
}
toggleUtilityFields('add');
filterByTab('all');
</script>
<?php include 'includes/footer.php'; ?>
</body>
</html>
