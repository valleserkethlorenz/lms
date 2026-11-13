(function(){
  const LEAGUE_ID = window.LEAGUE_ID || 0;
  const ACTION_URL = 'action/tournamentProgressionAction.php';
  const CSRF_TOKEN = window.CSRF_TOKEN || '';
  const $ = s => document.querySelector(s);
  let selectedTeamIds = new Set();

  // The Progression Manager is optional markup — bail out quietly when the page
  // does not render it so a missing control can never break the script.
  if (!$('#btnGetSuggestions') || !$('#btnConfirmProgression') || !$('#btnResetProgression') ||
      !$('#btnGenerateKnockout') || !$('#progressionAlert')) return;

  $('#btnGetSuggestions').addEventListener('click', async () => {
    const qpg = parseInt($('#qualifiersPerGroup').value) || 1;
    const btn = $('#btnGetSuggestions');
    btn.disabled = true; btn.textContent = 'Loading...';
    try {
      const fd = new FormData();
      fd.append('suggestProgression', '1'); fd.append('league_id', LEAGUE_ID);
      fd.append('qualifiers_per_group', qpg);
      fd.append('csrf_token', CSRF_TOKEN);
      const res = await fetch(ACTION_URL, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.status !== 200) { alert(data.msg || 'Error'); return; }
      selectedTeamIds.clear();
      (data.suggestions||[]).forEach(p => p.suggested.forEach(t => selectedTeamIds.add(t.team_id)));
      renderSuggestions(data.suggestions || []);
      renderConfirmed(data.confirmed_qualifiers || []);
      $('#suggestionsContainer').style.display = 'block';
      $('#progressionActions').style.display = 'flex';
      $('#progressionAlert').innerHTML = '<i class="bi bi-info-circle"></i> Review suggestions. Check/uncheck teams, then <strong>Confirm Selection</strong>.';
    } catch(e) { alert('Failed: ' + e.message); }
    finally { btn.disabled = false; btn.textContent = 'Get Suggestions'; }
  });

  function renderSuggestions(sugs) {
    const c = $('#suggestionsContainer');
    if (!sugs.length) { c.innerHTML = '<p class="text-muted">No data yet.</p>'; return; }
    let h = '';
    sugs.forEach(pool => {
      const gn = pool.label || (pool.group > 0 ? 'Group ' + pool.group : 'All Teams');
      h += '<div class="mb-3"><h6 class="fw-bold">' + gn + ' <small class="text-muted">(' + pool.total_teams + ' teams, ' + pool.qualifiers_requested + ' qual.)</small></h6>';
      h += '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-1"><thead class="table-light"><tr><th style="width:40px"></th><th>#</th><th>Team</th><th>Pts</th><th>W-D-L</th><th>Diff</th><th>Status</th></tr></thead><tbody>';
      pool.suggested.forEach(t => {
        const ck = selectedTeamIds.has(t.team_id) ? 'checked' : '';
        const bl = t.is_borderline ? ' <span class="badge bg-warning text-dark">TIE</span>' : '';
        h += '<tr class="table-success"><td><input type="checkbox" class="form-check-input team-check" data-id="' + t.team_id + '" ' + ck + '></td><td>' + t.rank + '</td><td class="fw-semibold">' + esc(t.team_name) + bl + '</td><td>' + t.points + '</td><td>' + t.wins + '-' + t.draws + '-' + t.losses + '</td><td>' + (t.differential>0?'+':'') + t.differential + '</td><td><span class="badge bg-primary">Suggested</span></td></tr>';
      });
      pool.borderline.forEach(t => {
        const ck = selectedTeamIds.has(t.team_id) ? 'checked' : '';
        h += '<tr class="table-warning"><td><input type="checkbox" class="form-check-input team-check" data-id="' + t.team_id + '" ' + ck + '></td><td>' + t.rank + '</td><td class="fw-semibold">' + esc(t.team_name) + ' <span class="badge bg-warning text-dark">TIE</span></td><td>' + t.points + '</td><td>' + t.wins + '-' + t.draws + '-' + t.losses + '</td><td>' + (t.differential>0?'+':'') + t.differential + '</td><td><span class="badge bg-secondary">Borderline</span></td></tr>';
      });
      pool.not_qualified.forEach(t => {
        const ck = selectedTeamIds.has(t.team_id) ? 'checked' : '';
        h += '<tr><td><input type="checkbox" class="form-check-input team-check" data-id="' + t.team_id + '" ' + ck + '></td><td>' + t.rank + '</td><td>' + esc(t.team_name) + '</td><td>' + t.points + '</td><td>' + t.wins + '-' + t.draws + '-' + t.losses + '</td><td>' + (t.differential>0?'+':'') + t.differential + '</td><td><span class="badge bg-light text-muted">Not qualified</span></td></tr>';
      });
      h += '</tbody></table></div></div>';
    });
    c.innerHTML = h;
    c.querySelectorAll('.team-check').forEach(cb => cb.addEventListener('change', () => {
      const tid = parseInt(cb.dataset.id);
      if (cb.checked) selectedTeamIds.add(tid); else selectedTeamIds.delete(tid);
      updateBtn();
    }));
    updateBtn();
  }

  function renderConfirmed(q) {
    const c = $('#confirmedContainer'), l = $('#confirmedList');
    if (!q.length) { c.style.display = 'none'; return; }
    c.style.display = 'block';
    l.innerHTML = q.map(x => '<span class="badge bg-success fs-6"><i class="bi bi-check2"></i>' + esc(x.team_name) + '</span>').join('');
  }

  function updateBtn() {
    const btn = $('#btnGenerateKnockout'), hint = $('#knockoutHint'), n = selectedTeamIds.size;
    if (n >= 2) { btn.disabled = false; hint.textContent = n + ' team(s) selected.'; hint.className = 'text-success'; }
    else { btn.disabled = true; hint.textContent = 'Select at least 2 teams.'; hint.className = 'text-muted'; }
  }

  $('#btnConfirmProgression').addEventListener('click', async () => {
    if (selectedTeamIds.size === 0) { alert('Select at least one qualifier before confirming.'); return; }
    if (!confirm('Confirm ' + selectedTeamIds.size + ' qualifier(s)?')) return;
    const btn = $('#btnConfirmProgression'); btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('confirmProgression', '1'); fd.append('league_id', LEAGUE_ID);
      fd.append('team_ids', Array.from(selectedTeamIds).join(','));
      fd.append('csrf_token', CSRF_TOKEN);
      const res = await fetch(ACTION_URL, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.status !== 200) { alert(data.msg || 'Error'); return; }
      alert('Confirmed! ' + data.confirmed_count + ' qualified.'); location.reload();
    } catch(e) { alert('Failed: ' + e.message); }
    finally { btn.disabled = false; }
  });

  $('#btnResetProgression').addEventListener('click', async () => {
    if (!confirm('Reset all progression decisions back to Undecided?')) return;
    try {
      const fd = new FormData();
      fd.append('resetProgression', '1'); fd.append('league_id', LEAGUE_ID);
      fd.append('csrf_token', CSRF_TOKEN);
      const res = await fetch(ACTION_URL, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.status !== 200) { alert(data.msg || 'Error'); return; }
      alert('Progression reset for ' + (data.reset_count || 0) + ' team(s).'); location.reload();
    } catch(e) { alert('Failed: ' + e.message); }
  });

  $('#btnGenerateKnockout').addEventListener('click', async () => {
    const venue = prompt('Venue (must be an active venue from Utilities):'); if (venue === null) return;
    if (!venue.trim()) { alert('A venue is required. Add an active venue under Utilities first.'); return; }
    const sd = prompt('Start date (YYYY-MM-DD):', new Date().toISOString().split('T')[0]); if (!sd) return;
    const btn = $('#btnGenerateKnockout'); btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('generateKnockoutFromConfirmed', '1'); fd.append('league_id', LEAGUE_ID);
      fd.append('venue', venue); fd.append('start_date', sd);
      fd.append('csrf_token', CSRF_TOKEN);
      const res = await fetch(ACTION_URL, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.status < 200 || data.status >= 300) { alert(data.msg || 'Error'); return; }
      alert('Generated! ' + data.saved + ' matches.'); window.location.href = 'schedule.php?id=' + LEAGUE_ID;
    } catch(e) { alert('Failed: ' + e.message); }
    finally { btn.disabled = false; }
  });

  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  // Load initial status
  (async () => {
    try {
      const fd = new FormData(); fd.append('getProgressionStatus', '1'); fd.append('league_id', LEAGUE_ID); fd.append('csrf_token', CSRF_TOKEN);
      const res = await fetch(ACTION_URL, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.status === 200 && data.qualified_count > 0) {
        $('#progressionAlert').innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> <strong>' + data.qualified_count + '</strong> confirmed. Click "Get Suggestions" to review.';
        $('#progressionAlert').className = 'alert alert-success mb-3';
      }
    } catch(e) {}
  })();
})();

