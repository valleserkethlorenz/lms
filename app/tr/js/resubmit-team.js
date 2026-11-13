/**
 * Correction resubmission page behaviour.
 *
 * Replaces the JavaScript that used to live inside the #editReapplyModal modal
 * in app/tr/view_team.php. Configuration is printed by app/tr/resubmit_team.php
 * into window.LMS_RESUBMIT:
 *   { teamId, api, redirect, minPlayers, maxPlayers, rosterIsFixed }
 *
 * The markup still calls addEditRow(), removeEditRow(this) and
 * validateReapplyForm() from inline handlers, exactly like the modal did, so the
 * row template below must stay in sync with resubmit_team.php.
 */
(function () {
    'use strict';

    var CFG = window.LMS_RESUBMIT || {};
    var form = document.getElementById('resubmitTeam');
    var tbody = document.getElementById('resubmitPlayerBody');
    if (!form || !tbody) return;

    var MIN = Number(CFG.minPlayers || 1);
    var MAX = Number(CFG.maxPlayers || 0);
    var FIXED = !!CFG.rosterIsFixed;
    var API = CFG.api || 'action/reapplyTeam.php';
    var REDIRECT = CFG.redirect || 'view_team.php';
    var ACCEPT = '.pdf,.jpg,.jpeg,.png,.bmp,.webp,application/pdf,image/jpeg,image/png,image/bmp,image/webp';

    // Floating confirmations come from the shared LMS toast component loaded by
    // app/tr/includes/footer.php (assets/js/lms-toast.js). The tiny fallback is
    // only used when that script could not be fetched.
    var Toast = window.Toast || {
        fire: function (params) { return Swal.fire(params); }
    };

    var uploading = false;
    var progressTimers = null;

    function rows() {
        return Array.prototype.slice.call(tbody.querySelectorAll('tr.player-edit-row'));
    }

    function rosterMessage(state) {
        if (FIXED) return 'This league requires exactly ' + MIN + ' player(s).';
        if (state === 'max') return 'This league allows a maximum of ' + MAX + ' players.';
        return 'Minimum ' + MIN + ' player(s) required.';
    }

    /* ------------------------------------------------------------------
       Roster validation summary
       ------------------------------------------------------------------ */
    function validateReapplyForm() {
        var summary = document.getElementById('resubmitValidationSummary');
        var list = document.getElementById('resubmitValidationList');
        if (!summary || !list) return;

        var playerCount = rows().filter(function (row) {
            var nameInput = row.querySelector('input[name="player_name[]"]');
            return nameInput && nameInput.value.trim() !== '';
        }).length;

        var issues = [];
        if (FIXED) {
            if (playerCount !== MIN) {
                issues.push('This league requires exactly ' + MIN + ' player(s). You entered ' + playerCount + '.');
            }
        } else {
            if (playerCount < MIN) {
                issues.push('Only ' + playerCount + ' player(s) entered. Minimum ' + MIN + ' required.');
            }
            if (MAX > 0 && playerCount > MAX) {
                issues.push(playerCount + ' player(s) entered. Maximum ' + MAX + ' allowed.');
            }
        }

        if (issues.length) {
            list.innerHTML = issues.map(function (issue) { return '<li>' + issue + '</li>'; }).join('');
            summary.classList.remove('d-none');
        } else {
            summary.classList.add('d-none');
        }
    }

    /* ------------------------------------------------------------------
       Add / remove rows
       ------------------------------------------------------------------ */
    function addEditRow() {
        if (uploading) return;
        if (MAX > 0 && rows().length >= MAX) {
            Swal.fire({
                icon: 'info',
                title: FIXED ? 'Fixed roster size' : 'Roster limit reached',
                text: rosterMessage('max')
            });
            return;
        }

        var row = document.createElement('tr');
        row.className = 'player-edit-row is-editable';
        row.innerHTML = ''
            + '<td><input type="hidden" name="player_id[]" value="">'
            + '<input type="text" name="player_name[]" class="form-control" required onchange="validateReapplyForm()"></td>'
            + '<td><input type="number" name="player_age[]" class="form-control" min="1" max="99" required onchange="validateReapplyForm()"></td>'
            + '<td><input type="text" name="player_role[]" class="form-control" required></td>'
            + '<td><input type="number" name="jersey_number[]" class="form-control" min="0" max="99" required></td>'
            + '<td><select name="gender[]" class="form-select" required onchange="validateReapplyForm()">'
            + '<option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></td>'
            + '<td><div class="doc-slots"><div class="doc-slot-missing">'
            + '<i class="bi bi-exclamation-circle" aria-hidden="true"></i> New player</div>'
            + '<input type="file" name="player_document[]" class="form-control form-control-sm" accept="' + ACCEPT + '" required></div></td>'
            + '<td class="text-center"><button type="button" class="btn btn-outline-danger resubmit-remove" '
            + 'onclick="removeEditRow(this)" aria-label="Remove player"><i class="bi bi-trash" aria-hidden="true"></i></button></td>';

        tbody.appendChild(row);
        var nameInput = row.querySelector('input[name="player_name[]"]');
        if (nameInput) nameInput.focus();
        validateReapplyForm();
    }

    function removeEditRow(button) {
        if (uploading) return;
        if (rows().length <= MIN) {
            Swal.fire({
                icon: 'info',
                title: 'Roster minimum',
                text: rosterMessage('min')
            });
            return;
        }

        var row = button.closest('tr.player-edit-row');
        var remarksRow = row ? row.previousElementSibling : null;
        if (remarksRow && remarksRow.classList.contains('correction-note')) remarksRow.remove();
        if (row) row.remove();
        validateReapplyForm();
    }

    window.addEditRow = addEditRow;
    window.removeEditRow = removeEditRow;
    window.validateReapplyForm = validateReapplyForm;


    /* ------------------------------------------------------------------
       Upload progress panel
       ------------------------------------------------------------------ */
    function startProgress() {
        var panel = document.getElementById('resubmitProgress');
        var title = document.getElementById('resubmitProgressTitle');
        var note = document.getElementById('resubmitProgressNote');
        if (!panel || !title || !note) return;

        panel.classList.add('is-active');
        title.textContent = 'Uploading documents…';
        note.textContent = 'Keep this tab open while we upload and check every file.';
        if (panel.scrollIntoView) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        progressTimers = [
            setTimeout(function () {
                title.textContent = 'Checking document quality…';
                note.textContent = 'Each upload is scanned for blur and readability.';
            }, 2200),
            setTimeout(function () {
                title.textContent = 'Still checking…';
                note.textContent = 'Large PDFs take a little longer — usually under 30 seconds.';
            }, 9000),
            setTimeout(function () {
                title.textContent = 'Almost done…';
                note.textContent = 'Saving your corrections and notifying the organizer.';
            }, 20000)
        ];
    }

    function stopProgress() {
        var panel = document.getElementById('resubmitProgress');
        if (panel) panel.classList.remove('is-active');
        if (progressTimers) {
            progressTimers.forEach(clearTimeout);
            progressTimers = null;
        }
    }


    /* ------------------------------------------------------------------
       Submit
       ------------------------------------------------------------------ */
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (uploading) return;

        var list = rows();
        if (list.length < MIN) {
            Swal.fire({ icon: 'warning', title: 'Roster incomplete', text: rosterMessage('min') });
            return;
        }

        var summary = document.getElementById('resubmitValidationSummary');
        if (summary && !summary.classList.contains('d-none')) {
            Swal.fire({ icon: 'warning', title: 'Fix the issues first', text: 'Please resolve the highlighted roster issues before resubmitting.' });
            return;
        }

        for (var i = 0; i < list.length; i++) {
            var pidInput = list[i].querySelector('input[name="player_id[]"]');
            var fileInput = list[i].querySelector('input[name="player_document[]"]');
            if (pidInput && pidInput.value === '' && fileInput && fileInput.files.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Missing document', text: 'New players must have a document uploaded.' });
                return;
            }
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        var submitBtn = document.getElementById('resubmitSubmit');
        var originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        uploading = true;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Processing…';
        }
        startProgress();

        var controller = new AbortController();
        var timeoutId = setTimeout(function () { controller.abort(); }, 120000);
        var settled = false;

        function finish() {
            settled = true;
            uploading = false;
            clearTimeout(timeoutId);
            stopProgress();
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }

        fetch(API, { method: 'POST', body: new FormData(form), signal: controller.signal })
            .then(function (response) { return response.text(); })
            .then(function (text) {
                if (settled) return;

                var data;
                try {
                    data = JSON.parse(text.trim());
                } catch (error) {
                    finish();
                    Swal.fire({
                        icon: 'error',
                        title: 'Server error',
                        html: '<pre style="text-align:left;font-size:12px;">'
                            + text.replace(/[<>&]/g, function (c) {
                                return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c];
                            })
                            + '</pre>'
                    });
                    return;
                }

                if (Number(data.status) === 200) {
                    clearTimeout(timeoutId);
                    uploading = false;
                    stopProgress();
                    Swal.fire({
                        icon: 'success',
                        title: 'Application resubmitted',
                        text: data.msg || 'Re-application submitted successfully.',
                        confirmButtonText: 'Back to my application'
                    }).then(function () {
                        window.location.href = REDIRECT;
                    });
                    return;
                }

                finish();
                Toast.fire({ icon: 'error', title: data.msg || 'The application could not be resubmitted.' });
            })
            .catch(function (error) {
                if (settled) return;
                finish();
                var message = error && error.name === 'AbortError'
                    ? 'The upload took longer than two minutes. Check your connection and try again.'
                    : 'Something went wrong while resubmitting. Please try again.';
                Swal.fire({ icon: 'error', title: 'Resubmission failed', text: message });
            });
    });

    /* ------------------------------------------------------------------
       Guard against closing the tab mid-upload
       ------------------------------------------------------------------ */
    window.addEventListener('beforeunload', function (event) {
        if (!uploading) return;
        event.preventDefault();
        event.returnValue = 'Your corrected application is still uploading.';
        return event.returnValue;
    });

    validateReapplyForm();
})();

