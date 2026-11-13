<?php
require_once __DIR__.'/../../config/dbconnect.php';
require_once __DIR__.'/../../config/auth_guard.php';
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
requireRole([1]); requireActiveAccount($conn);
$userId=currentUserId(); $id=(int)($_GET['id'] ?? 0);
$stmt=$conn->prepare("SELECT * FROM tbl_league WHERE id=? AND created_by=? AND publication_status='Draft' AND (is_archived=0 OR is_archived IS NULL)");
$stmt->bind_param('ii',$id,$userId); $stmt->execute(); $league=$stmt->get_result()->fetch_assoc(); $stmt->close();
if(!$league){
    $_SESSION['league_flash'] = ['danger', 'You can edit only your own active Draft leagues.'];
    header('Location: league.php'); exit;
}
$utilities=['sport'=>[],'division'=>[],'venue'=>[],'category'=>[],'document_type'=>[]];
$res=$conn->query("SELECT id,category,name,min_age,max_age,default_min_players,default_max_players,applies_to FROM tbl_utility WHERE is_active=1 ORDER BY category,name"); while($u=$res->fetch_assoc()) if(isset($utilities[$u['category']]))$utilities[$u['category']][]=$u;
$selectedRequirements=[]; $req=$conn->prepare("SELECT document_type_id,applies_to,instructions FROM tbl_league_document_requirement WHERE league_id=?"); $req->bind_param('i',$id); $req->execute(); $rr=$req->get_result(); while($r=$rr->fetch_assoc())$selectedRequirements[$r['document_type_id']]=$r; $req->close();
$guidelineDocuments=[]; $docs=$conn->prepare("SELECT id,stored_name,original_name,created_at FROM tbl_league_guideline_document WHERE league_id=? AND is_archived=0 ORDER BY id"); $docs->bind_param('i',$id); $docs->execute(); $docResult=$docs->get_result(); while($doc=$docResult->fetch_assoc())$guidelineDocuments[]=$doc; $docs->close();
include 'includes/header.php';
?>
<style>
/* Keep Edit League typography uniform despite legacy Organizer form overrides. */
#editDraftForm,
#editDraftForm .form-label,
#editDraftForm .form-text,
#editDraftForm .form-control,
#editDraftForm .form-select,
#editDraftForm option,
#editDraftForm .btn {
    font-family: "LMS Poppins", head, system-ui, -apple-system, "Segoe UI", sans-serif !important;
}
#editDraftForm .form-label {
    font-size: 0.95rem;
    font-weight: 600 !important;
    line-height: 1.4;
    text-transform: none;
}
#editDraftForm .form-control,
#editDraftForm .form-select {
    font-size: 0.95rem;
    font-weight: 400 !important;
    line-height: 1.5;
}
#editDraftForm textarea.form-control {
    min-height: 100px;
}
#editDraftForm .form-text {
    font-size: 0.85rem;
    font-weight: 400;
}
#editDraftForm .btn {
    font-size: 0.95rem;
    font-weight: 500;
}
</style>
<body id="page-top" class="body"><?php include 'includes/bar.php'; ?>
<div class="container-fluid"><div class="row p-3"><div class="card cards"><div class="card-body">
<div class="d-flex justify-content-between align-items-center"><div><a href="league.php" class="text-dark fw-bold">&lt; Back</a><h4 class="mt-3 mb-0">Edit Draft League</h4><small class="text-muted"></small></div><span class="badge bg-secondary fs-6">Draft</span></div><hr> 
<form id="editDraftForm" class="row g-3" enctype="multipart/form-data">
<input type="hidden" name="updateLeagueDraft" value="1"><input type="hidden" name="league_id" value="<?= $id ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
<div class="col-md-8"><label class="form-label">League Name</label><input name="league_name" class="form-control" maxlength="255" value="<?= htmlspecialchars($league['league_name']) ?>" required></div>
<div class="col-md-4"><label class="form-label">Replace League Image</label><input type="file" name="league_image" class="form-control" accept="image/*"><div class="form-text">Leave blank to keep the current image.</div></div>
<div class="col-md-4"><label class="form-label">Sport</label><select name="sport" id="editLeagueSport" class="form-select" required><?php foreach($utilities['sport'] as $u): ?><option value="<?= htmlspecialchars($u['name']) ?>" data-default-min-players="<?= htmlspecialchars((string)($u['default_min_players'] ?? '')) ?>" data-default-max-players="<?= htmlspecialchars((string)($u['default_max_players'] ?? '')) ?>" <?= $league['sport']===$u['name']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Age Division</label><select name="division" class="form-select" required><?php foreach($utilities['division'] as $u): ?><option value="<?= htmlspecialchars($u['name']) ?>" <?= $league['division']===$u['name']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?> (<?= (int)$u['min_age'] ?>–<?= (int)$u['max_age'] ?>)</option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Category</label><select name="category" class="form-select" required><?php foreach($utilities['category'] as $u): ?><option <?= $league['category']===$u['name']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option><?php endforeach; ?></select></div>

<div class="col-md-4"><label class="form-label">Venue</label><select name="venue" class="form-select" required><?php foreach($utilities['venue'] as $u): ?><option <?= $league['venue']===$u['name']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label class="form-label">Maximum Teams</label><input type="number" name="max_team" min="2" max="256" class="form-control" value="<?= (int)$league['max_team'] ?>" required></div>
<div class="col-md-2"><label class="form-label">Minimum Players</label><input type="number" name="min_player" min="1" max="100" class="form-control" value="<?= (int)($league['min_player'] ?? 5) ?>" required></div>
<div class="col-md-2"><label class="form-label">Maximum Players</label><input type="number" name="max_player" min="1" max="100" class="form-control" value="<?= (int)$league['max_player'] ?>" required><div class="form-text" id="editRosterSizeHint">Must be at least the minimum.</div></div>
<?php foreach(['regis_start'=>'Registration Start','regis_end'=>'Registration End','start_date'=>'League Start','end_date'=>'League End'] as $field=>$label): ?><div class="col-md-3"><label class="form-label"><?= $label ?></label><input type="date" name="<?= $field ?>" class="form-control" value="<?= htmlspecialchars($league[$field]) ?>" required></div><?php endforeach; ?>
<div class="col-md-6"><label class="form-label">About League</label><textarea name="descrip" maxlength="255" class="form-control" required><?= htmlspecialchars($league['descrip']) ?></textarea></div><div class="col-md-6"><label class="form-label">Other Information (optional)</label><textarea name="add_info" maxlength="255" class="form-control" placeholder="Anything else teams should know"><?= htmlspecialchars($league['add_info']) ?></textarea></div>
<div class="col-12"><label class="form-label">League Guideline Documents</label><div class="border rounded p-3"><?php if($guidelineDocuments): ?><div class="mb-3"><?php foreach($guidelineDocuments as $doc): ?><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom py-2"><a href="../../assets/documents/<?= rawurlencode($doc['stored_name']) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars($doc['original_name']) ?></a><div class="form-check"><input class="form-check-input" type="checkbox" name="remove_guideline_ids[]" value="<?= (int)$doc['id'] ?>" id="removeGuide<?= (int)$doc['id'] ?>"><label class="form-check-label text-danger" for="removeGuide<?= (int)$doc['id'] ?>">Remove</label></div></div><?php endforeach; ?></div><?php else: ?><div class="text-muted mb-3">No guideline documents have been added.</div><?php endif; ?><div id="guidelineInputs"><div class="input-group mb-2"><input type="file" name="guideline_documents[]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png"><button class="btn btn-outline-danger remove-guideline-input" type="button" style="display:none">Remove</button></div></div><button type="button" class="btn btn-sm btn-success mt-1" id="addGuidelineInput"><i class="bi bi-plus-circle me-1"></i>Add Guideline Document</button><div class="form-text">Up to 10 MB per file. Supported: PDF, Office files, text, JPG, and PNG.</div></div></div>
<div class="col-12"><div class="mb-2"><div class="form-label fw-semibold mb-1">Registration Document Requirements</div></div><div class="border rounded overflow-hidden"><?php if(!$utilities['document_type']): ?><div class="p-3 text-muted">No active document types are configured.</div><?php else: ?><div class="row g-0 bg-light border-bottom px-3 py-2 d-none d-md-flex fw-semibold"><div class="col-md-4">Document type</div><div class="col-md-3">Scope</div><div class="col-md-5">Instructions</div></div><?php foreach($utilities['document_type'] as $u): $selected=$selectedRequirements[$u['id']]??null; $scopeLabel=$u['applies_to']==='team'?'Per Team':'Per Player'; $scopeIcon=$u['applies_to']==='team'?'bi-people-fill':'bi-person-fill'; $scopeBg=$u['applies_to']==='team'?'#fef3c7':'#dbeafe'; $scopeColor=$u['applies_to']==='team'?'#92400e':'#1e40af'; ?><div class="row g-3 align-items-center px-3 py-3 border-bottom"><div class="col-md-4"><div class="form-check"><input class="form-check-input doc-required-toggle" type="checkbox" name="document_requirement[<?= (int)$u['id'] ?>]" value="1" id="doc_req_edit_<?= (int)$u['id'] ?>" <?= $selected?'checked':'' ?>><label class="form-check-label fw-semibold" for="doc_req_edit_<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?></label></div></div><div class="col-md-3"><label class="form-label fw-semibold d-md-none">Scope</label><input type="hidden" name="document_requirement_scope[<?= (int)$u['id'] ?>]" value="<?= htmlspecialchars($u['applies_to']??'player') ?>"><span class="badge" style="background:<?= $scopeBg ?>;color:<?= $scopeColor ?>;font-size:0.85rem;padding:6px 12px;"><i class="bi <?= $scopeIcon ?> me-1"></i><?= $scopeLabel ?></span></div><div class="col-md-5"><label class="form-label fw-semibold d-md-none">Instructions</label><input name="document_instructions[<?= (int)$u['id'] ?>]" maxlength="500" class="form-control" placeholder="Optional notes or file instructions" value="<?= htmlspecialchars($selected['instructions']??'') ?>"></div></div><?php endforeach; ?><?php endif; ?></div></div>
<div class="col-12 d-flex justify-content-end gap-2"><a href="league.php" class="btn btn-secondary">Cancel</a><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Draft Changes</button></div>
</form></div></div></div></div>
<script>
// A fixed roster (min === max) must read "exactly N players", never "N–N".
function updateEditRosterHint() {
    const minInput = document.querySelector('#editDraftForm [name="min_player"]');
    const maxInput = document.querySelector('#editDraftForm [name="max_player"]');
    const hint = document.getElementById('editRosterSizeHint');
    if (!minInput || !maxInput || !hint) return;
    const minimum = parseInt(minInput.value, 10);
    const maximum = parseInt(maxInput.value, 10);
    if (!Number.isInteger(minimum) || !Number.isInteger(maximum) || minimum < 1 || maximum < 1) {
        hint.textContent = 'Set both boxes to the same number for a fixed roster (e.g. Pickleball: 2 and 2).';
    } else if (minimum > maximum) {
        hint.textContent = 'Minimum players cannot be greater than maximum players.';
    } else if (minimum === maximum) {
        hint.textContent = `Fixed roster size — every team must register exactly ${minimum} player${minimum === 1 ? '' : 's'}.`;
    } else {
        hint.textContent = `Teams may register ${minimum}–${maximum} players.`;
    }
}
document.querySelectorAll('#editDraftForm [name="min_player"], #editDraftForm [name="max_player"]').forEach(function (input) {
    input.addEventListener('input', updateEditRosterHint);
});
updateEditRosterHint();
document.getElementById('editLeagueSport').addEventListener('change', function () {
    const option = this.selectedOptions[0];
    const minimum = Number(option?.dataset.defaultMinPlayers);
    const maximum = Number(option?.dataset.defaultMaxPlayers);
    if (Number.isInteger(minimum) && Number.isInteger(maximum) && minimum >= 1 && maximum >= minimum && maximum <= 100) {
        document.querySelector('#editDraftForm [name="min_player"]').value = minimum;
        document.querySelector('#editDraftForm [name="max_player"]').value = maximum;
    }
    updateEditRosterHint();
});
</script>
<?php include 'includes/footer.php'; ?><script>const guidelineInputs=document.getElementById('guidelineInputs');function syncGuidelineRemoveButtons(){const rows=guidelineInputs.querySelectorAll('.input-group');rows.forEach(row=>row.querySelector('.remove-guideline-input').style.display=rows.length>1?'block':'none');}document.getElementById('addGuidelineInput').addEventListener('click',()=>{const row=document.createElement('div');row.className='input-group mb-2';row.innerHTML='<input type="file" name="guideline_documents[]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png"><button class="btn btn-outline-danger remove-guideline-input" type="button">Remove</button>';guidelineInputs.appendChild(row);syncGuidelineRemoveButtons();});guidelineInputs.addEventListener('click',event=>{if(event.target.classList.contains('remove-guideline-input')){event.target.closest('.input-group').remove();syncGuidelineRemoveButtons();}});document.getElementById('editDraftForm').addEventListener('submit',async function(e){e.preventDefault();const minimum=Number(this.elements.min_player.value),maximum=Number(this.elements.max_player.value);if(!Number.isInteger(minimum)||!Number.isInteger(maximum)||minimum<1||maximum>100||minimum>maximum){Swal.fire('Invalid roster range','Minimum players must be from 1 to 100 and cannot exceed maximum players.','error');return;}const button=this.querySelector('button[type=submit]');button.disabled=true;try{const response=await fetch('action/updateLeagueDraft.php',{method:'POST',body:new FormData(this)});const data=await response.json();if(response.ok&&data.status===200)Swal.fire('Saved',data.msg,'success').then(()=>location.href='league.php');else Swal.fire('Unable to save',data.msg||'Draft update failed.','error');}catch(error){Swal.fire('Error','Server request failed.','error');}finally{button.disabled=false;}});</script></body></html>
