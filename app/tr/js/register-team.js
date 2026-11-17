/**
 * Team registration page behaviour.
 *
 * Replaces the JavaScript that used to live inside
 * components/modal/registeam.php. Configuration is printed by
 * app/tr/register_team.php into window.LMS_REGISTRATION:
 *   { leagueId, userId, api, today, redirect, loginUrl, requirements, playerDocuments }
 *
 * The roster builder, validation and document-quality handling are copied
 * verbatim from the modal so behaviour stays identical. Functions referenced by
 * inline handlers (addRow, removeRow, validateForm) stay in the global scope.
 */

const REG_CFG = window.LMS_REGISTRATION || {};
const REG_REQUIREMENTS = REG_CFG.requirements || {};
const REG_MIN_ROWS = Math.max(1, Number(REG_REQUIREMENTS.min_players || 5));
const REG_PLAYER_DOCUMENTS = Array.isArray(REG_CFG.playerDocuments) ? REG_CFG.playerDocuments : [];
const REG_TODAY = REG_CFG.today || new Date().toISOString().slice(0, 10);

const REG_DRAFT_VERSION = 1;
const REG_DRAFT_TTL_DAYS = 7;
const REG_DRAFT_PREFIX = 'lms_team_reg_draft';

const regForm = document.getElementById('registerTeam');
let regSubmitted = false;
let regSubmitting = false;
let regDraftTimer = null;
let regExiting = false;

/* -------------------------------------------------------------------------
   File selection feedback
   ------------------------------------------------------------------------- */
if (regForm) {
    regForm.addEventListener('change', event => {
        const input = event.target.closest('input[type="file"]');
        if (!input) return;
        showFileSelectionFeedback(input);
        syncFileBlob(input);

        // Large phone photos are downscaled in place; the feedback line under
        // the input reports the new size and the draft keeps the smaller copy.
        optimiseFileInput(input).then(optimised => {
            if (optimised) syncFileBlob(input);
            // Documents and dates only change on `change`, so refresh the
            // summary here as well as on `input`.
            validateForm();
        });
    });
}

function showFileSelectionFeedback(input) {
    let feedback = input.parentElement.querySelector(':scope > .file-selection-feedback');
    if (!feedback) {
        feedback = document.createElement('small');
        feedback.className = 'file-selection-feedback';
        input.insertAdjacentElement('afterend', feedback);
    }

    input.setCustomValidity('');
    feedback.className = 'file-selection-feedback';
    const file = input.files?.[0];
    if (!file) {
        feedback.textContent = '';
        return;
    }

    const isTeamImage = input.name === 'team_image';
    const maxBytes = (isTeamImage ? 5 : 10) * 1024 * 1024;
    const extension = (file.name.split('.').pop() || '').toLowerCase();
    const allowedExtensions = isTeamImage
        ? ['jpg','jpeg','png','gif','webp']
        : ['pdf','jpg','jpeg','png','bmp','webp'];
    let error = '';
    if (!allowedExtensions.includes(extension)) error = `Unsupported file type. ${isTeamImage ? 'Choose JPG, PNG, GIF, or WebP.' : 'Choose a PDF or image file.'}`;
    else if (file.size <= 0) error = 'The selected file is empty.';
    else if (file.size > maxBytes) error = `File exceeds the ${isTeamImage ? 5 : 10} MB limit.`;

    if (error) {
        input.setCustomValidity(error);
        input.classList.add('document-quality-failed');
        input.setAttribute('aria-invalid', 'true');
        feedback.classList.add('invalid');
        feedback.textContent = error;
    } else {
        // The native file control already displays the selected filename.
        // Keep this area reserved only for actionable validation errors.
        input.classList.remove('document-quality-failed');
        input.removeAttribute('aria-invalid');
        feedback.textContent = '';
    }
}

/* -------------------------------------------------------------------------
   Upload size (mobile photos)
   -------------------------------------------------------------------------
   A phone photo is 3-8 MB per file and the form allows one document per
   player, so a normal registration is tens of megabytes. Hosting limits are
   far smaller (Heroku terminates uploads at ~30 s and its PHP post_max_size is
   8 MB by default), which is why those submissions died with a bare
   "Failed to fetch". Downscaling the picture in the browser fixes the cause:
   ~4 MB becomes ~0.3 MB and a document photo stays perfectly legible.

   Everything degrades to "send the original" if the browser cannot decode the
   image, so a PDF or an exotic format is never blocked here.
   ------------------------------------------------------------------------- */
const REG_IMAGE_OPTIMISE_BYTES = 350 * 1024;   // leave already-small files alone
const REG_IMAGE_MAX_EDGE = 1600;               // px on the long edge
const REG_IMAGE_QUALITY = 0.8;

function isOptimisableImage(file) {
    if (!file) return false;
    const type = String(file.type || '').toLowerCase();
    if (type) {
        return type === 'image/jpeg' || type === 'image/jpg' || type === 'image/png' || type === 'image/webp';
    }
    // Some mobile pickers and draft-restored blobs carry no MIME type; fall back
    // to the extension so those photos are still downscaled before upload.
    return /\.(jpe?g|png|webp)$/i.test(String(file.name || ''));
}

/** Resolves with a smaller JPEG copy, or null when the original should be used. */
function optimiseImageFile(file) {
    return new Promise(resolve => {
        if (!isOptimisableImage(file) || file.size <= REG_IMAGE_OPTIMISE_BYTES) return resolve(null);
        if (typeof document === 'undefined' || !document.createElement) return resolve(null);

        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            URL.revokeObjectURL(url);
            try {
                const naturalWidth = image.naturalWidth || image.width || 1;
                const naturalHeight = image.naturalHeight || image.height || 1;
                const scale = Math.min(1, REG_IMAGE_MAX_EDGE / Math.max(naturalWidth, naturalHeight));
                const width = Math.max(1, Math.round(naturalWidth * scale));
                const height = Math.max(1, Math.round(naturalHeight * scale));

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const context = canvas.getContext('2d');
                if (!context) return resolve(null);

                // White backing: PNG screenshots with transparency stay readable.
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, width, height);
                context.drawImage(image, 0, 0, width, height);

                canvas.toBlob(blob => {
                    if (!blob || blob.size >= file.size) return resolve(null);
                    const name = String(file.name || 'document').replace(/\.[^.]+$/, '') + '.jpg';
                    resolve(new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() }));
                }, 'image/jpeg', REG_IMAGE_QUALITY);
            } catch (error) {
                resolve(null);
            }
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(null);
        };
        image.src = url;
    });
}

/** Swaps the selected file for its optimised copy. No-op when not applicable. */
function optimiseFileInput(input) {
    if (!input || !input.files || !input.files.length) return Promise.resolve(false);
    if (typeof DataTransfer === 'undefined') return Promise.resolve(false);

    const file = input.files[0];
    return optimiseImageFile(file).then(optimised => {
        if (!optimised) return false;
        try {
            const transfer = new DataTransfer();
            transfer.items.add(optimised);
            input.files = transfer.files;
        } catch (error) {
            return false;
        }

        const feedback = input.parentElement?.querySelector(':scope > .file-selection-feedback');
        if (feedback) {
            const before = (file.size / 1048576).toFixed(1);
            const after = (optimised.size / 1048576).toFixed(1);
            feedback.textContent = `Optimised for upload: ${before} MB → ${after} MB.`;
            feedback.classList.remove('invalid');
        }
        input.removeAttribute('aria-invalid');
        return true;
    });
}

/** Optimise every selected image on the form (used right before submit). */
function optimiseSelectedImages() {
    if (!regForm) return Promise.resolve();
    const inputs = Array.from(regForm.querySelectorAll('input[type="file"]'))
        .filter(input => input.files && input.files.length);
    return Promise.all(inputs.map(input => optimiseFileInput(input))).then(() => undefined);
}

/* -------------------------------------------------------------------------
   Roster
   ------------------------------------------------------------------------- */
function initRoster(rowCount) {
    const tbody = document.getElementById('playerBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    for (let i = 0; i < rowCount; i++) {
        addRow(false);
    }
    updatePlayerRowCount();
}

function addRow(showLimitMessage = true) {
    let tbody = document.getElementById("playerBody");
    const maximumPlayers = Number(REG_REQUIREMENTS.max_players || 0);

    if (maximumPlayers > 0 && tbody.rows.length >= maximumPlayers) {
        updatePlayerRowCount();
        if (showLimitMessage) {
            Swal.fire({
                icon: 'info',
                title: 'Roster Limit Reached',
                text: `This league allows a maximum of ${maximumPlayers} players.`,
                confirmButtonText: 'OK'
            });
        }
        return;
    }

    let row = document.createElement("tr");


    const documentInputs = REG_PLAYER_DOCUMENTS.length
      ? REG_PLAYER_DOCUMENTS.map(doc => `<div class="player-document-field document-field-block mb-2"><label class="small fw-semibold d-block">${escapeDocHtml(doc.name)}${doc.is_required == 1 ? '<span class="required-marker" aria-hidden="true">*</span><span class="visually-hidden"> required</span>' : ''}</label><input type="file" data-document-scope="player" data-document-requirement="${doc.id}" name="player_documents[${doc.id}][]" class="form-control form-control-sm mb-1" accept=".pdf,.jpg,.jpeg,.png,.bmp,.webp,application/pdf,image/jpeg,image/png,image/bmp,image/webp" ${doc.is_required == 1 ? 'required' : ''}><small class="text-muted d-block">${escapeDocHtml(doc.instructions || '')}</small><small class="text-muted d-block">PDF, JPG, PNG, BMP, or WebP · Maximum 10 MB.</small></div>`).join('')
      : `<div class="player-document-field document-field-block"><label class="small fw-semibold d-block">Eligibility document<span class="required-marker" aria-hidden="true">*</span><span class="visually-hidden"> required</span></label><input type="file" data-document-scope="legacy_player" name="player_document[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.bmp,.webp,application/pdf,image/jpeg,image/png,image/bmp,image/webp" required><small class="text-muted d-block mt-1">PDF, JPG, PNG, BMP, or WebP · Maximum 10 MB.</small></div>`;
    row.innerHTML = `
        <td data-label="Player name"><input type="text" name="player_name[]" class="form-control" minlength="2" maxlength="100" placeholder="Full name" required oninput="validateForm()"></td>
        <td data-label="Birthdate"><input type="date" name="player_birthdate[]" class="form-control" max="${REG_TODAY}" required onchange="validateForm()"></td>
        <td data-label="Position / role">
             <input type="text" name="player_role[]" class="form-control" minlength="2" maxlength="50" placeholder="Position / role" required>
        </td>
        <td data-label="Jersey no."><input type="number" name="jersey_number[]" class="form-control" min="0" max="999" step="1" placeholder="No." required oninput="validateForm()"></td>
        <td data-label="Gender">
            <select name="gender[]" class="form-select" required onchange="validateForm()">
                <option value="">Select</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
        </td>
        <td data-label="Documents">
            ${documentInputs}
        </td>
        <td data-label="" class="text-center">
            <button type="button" class="remove-player-button" onclick="removeRow(this)" title="Remove player" aria-label="Remove player"><i class="bi bi-trash3"></i></button>
        </td>
    `;

    tbody.appendChild(row);
    updatePlayerRowCount();
    validateForm();
    scheduleDraftSave();
}

function updatePlayerRowCount() {
    const rowCount = document.getElementById('playerBody').rows.length;
    const maximumPlayers = Number(REG_REQUIREMENTS.max_players || 0);
    const countLabel = document.getElementById('playerRowCount');
    const addButton = document.getElementById('addPlayerButton');

    countLabel.textContent = maximumPlayers > 0
        ? `${rowCount} of ${maximumPlayers} player rows`
        : `${rowCount} player rows`;
    addButton.disabled = maximumPlayers > 0 && rowCount >= maximumPlayers;
    addButton.setAttribute('aria-disabled', addButton.disabled ? 'true' : 'false');
}

function escapeDocHtml(value) {
    const div=document.createElement('div'); div.textContent=value ?? ''; return div.innerHTML;
}

/* -------------------------------------------------------------------------
   Document quality + roster validation
   ------------------------------------------------------------------------- */
function clearDocumentQualityErrors() {
    document.querySelectorAll('#registerTeam .document-quality-failed').forEach(input => {
        input.classList.remove('document-quality-failed');
        input.removeAttribute('aria-invalid');
        input.removeAttribute('data-document-rejected');
    });
    document.querySelectorAll('#registerTeam .document-quality-message').forEach(message => message.remove());
    document.querySelectorAll('#registerTeam .document-quality-block-failed').forEach(block => block.classList.remove('document-quality-block-failed'));
}

function findFailedDocumentInput(error) {
    const scope = String(error.scope || '');
    if (scope === 'team') {
        return Array.from(document.querySelectorAll('#registerTeam input[type="file"][data-document-scope="team"]'))
            .find(input => input.dataset.documentRequirement === String(error.requirement_id)) || null;
    }
    if (scope === 'player' || scope === 'legacy_player') {
        const row = document.querySelectorAll('#playerBody tr')[Number(error.player_index ?? error.index ?? 0)];
        if (!row) return null;
        return Array.from(row.querySelectorAll('input[type="file"]')).find(input => {
            if (input.dataset.documentScope !== scope) return false;
            return scope === 'legacy_player' || input.dataset.documentRequirement === String(error.requirement_id);
        }) || null;
    }
    return Array.from(document.querySelectorAll('#registerTeam input[type="file"]'))
        .filter(input => input.name === String(error.name || ''))[Number(error.index ?? 0)] || null;
}

function showDocumentQualityErrors(errors) {
    clearDocumentQualityErrors();
    let firstFailedInput = null;
    (Array.isArray(errors) ? errors : []).forEach(error => {
        const input = findFailedDocumentInput(error);
        if (!input) return;

        input.classList.add('document-quality-failed');
        input.setAttribute('aria-invalid', 'true');
        // The checker rejected this upload: lock the slot until a new file is
        // chosen so the same document cannot be re-submitted unchanged.
        input.setAttribute('data-document-rejected', '1');
        input.closest('.document-field-block')?.classList.add('document-quality-block-failed');
        const message = document.createElement('small');
        message.className = 'document-quality-message';
        message.setAttribute('role', 'alert');
        message.textContent = error.message || 'This document appears to be unreadable or severely blurry.';
        input.insertAdjacentElement('afterend', message);
        if (!firstFailedInput) firstFailedInput = input;
    });

    if (firstFailedInput) {
        firstFailedInput.scrollIntoView({behavior:'smooth', block:'center'});
        window.setTimeout(() => firstFailedInput.focus({preventScroll:true}), 300);
    }
    return firstFailedInput;
}

function showDocumentErrorFallback(message) {
    if (!/document|file|upload|blurr|quality|unreadable|unsupported/i.test(String(message || ''))) return;
    const selectedInputs = Array.from(document.querySelectorAll('#registerTeam input[type="file"]'))
        .filter(input => input.name !== 'team_image' && input.files?.length);
    selectedInputs.forEach(input => {
        input.classList.add('document-quality-failed');
        input.setAttribute('aria-invalid', 'true');
        input.closest('.document-field-block')?.classList.add('document-quality-block-failed');
    });
    selectedInputs[0]?.scrollIntoView({behavior:'smooth', block:'center'});
}

/**
 * File inputs the checker rejected that still hold the rejected upload.
 *
 * The lock is cleared by the file input's change handler, so the only way to
 * submit again is to pick a different (readable) document. Without it the
 * rejected file stayed attached and the same blurry upload could be sent a
 * second time without the representative touching the field.
 */
function rejectedDocumentInputs() {
    if (!regForm) return [];
    return Array.from(regForm.querySelectorAll('input[type="file"][data-document-rejected="1"]'))
        .filter(input => input.files && input.files.length);
}

if (regForm) {
    regForm.addEventListener('change', event => {
        const input = event.target.closest('input[type="file"]');
        if (!input) return;
        input.removeAttribute('data-document-rejected');
        input.parentElement.querySelectorAll(':scope > .document-quality-message').forEach(message => message.remove());
        input.closest('.document-field-block')?.classList.remove('document-quality-block-failed');
        clearDraftFileNote(input);
    });
}

function removeRow(btn) {
    let tbody = document.getElementById("playerBody");

    if (tbody.rows.length <= REG_MIN_ROWS) {
        alert(`Minimum ${REG_MIN_ROWS} player${REG_MIN_ROWS === 1 ? '' : 's'} required!`);
        return;
    }

    btn.closest("tr").remove();
    updatePlayerRowCount();
    validateForm();
    scheduleDraftSave();
    rebuildFileBlobs();
}

/**
 * Human label for one control inside the registration form.
 * Used by the validation summary so a phone user is told *which* player and
 * *which* document still needs attention (the old summary only listed roster
 * counts, while the browser silently blocked on the off-screen file inputs).
 */
function fieldLabel(input) {
    const row = input.closest('tr');
    const rows = Array.from(document.querySelectorAll('#playerBody tr'));
    const rowIndex = row ? rows.indexOf(row) + 1 : 0;
    const labels = {
        'team_name': 'Team name',
        'team_image': 'Team logo / image',
        'barangay': 'Barangay / school',
        'player_name[]': 'Player name',
        'player_birthdate[]': 'Birthdate',
        'player_role[]': 'Position / role',
        'jersey_number[]': 'Jersey number',
        'gender[]': 'Gender'
    };
    const name = input.getAttribute('name') || '';
    if (Object.prototype.hasOwnProperty.call(labels, name)) {
        return rowIndex ? `Player ${rowIndex} — ${labels[name]}` : labels[name];
    }

    const block = input.closest('.document-field-block');
    const labelEl = block ? block.querySelector('label') : null;
    const docLabel = labelEl ? labelEl.textContent.replace(/\*/g, '').trim() : 'Document';
    return rowIndex ? `Player ${rowIndex} — ${docLabel}` : docLabel;
}

/**
 * Every required control that is still invalid, as readable lines.
 *
 * The form is novalidate: the browser bubble used to be the only feedback, and
 * on a phone the required document inputs sit off-screen in the roster table,
 * so tapping Submit appeared to do nothing.
 */
function collectRequiredFieldIssues() {
    const issues = [];
    const seen = new Set();

    document.querySelectorAll('#registerTeam [required]').forEach(input => {
        if (input.disabled || input.type === 'hidden' || input.type === 'submit') return;
        if (input.checkValidity()) return;

        const label = fieldLabel(input);
        let message;
        if (input.type === 'file') {
            message = `${label}: choose a file.`;
        } else if (input.validity && input.validity.valueMissing) {
            message = `${label}: required.`;
        } else {
            message = `${label}: ${input.validationMessage || 'invalid value'}.`;
        }

        const key = `${label}|${message}`;
        if (seen.has(key)) return;
        seen.add(key);
        issues.push(message);

        input.classList.add('player-field-invalid');
        input.setAttribute('aria-invalid', 'true');
    });

    return issues;
}

function validateForm(includeRequired = false) {
    let issues = [];
    let tbody = document.getElementById("playerBody");
    if (!tbody) return;
    let rows = tbody.querySelectorAll("tr");

    let playerCount = 0;
    let maleCount = 0;
    let femaleCount = 0;
    let ageIssues = [];
    let playerNames = new Map();
    let jerseyNumbers = new Map();

    // Clear previous field highlights
    rows.forEach(row => {
        row.querySelectorAll('.player-field-invalid').forEach(el => el.classList.remove('player-field-invalid'));
    });

    rows.forEach((row, index) => {
        let nameInput = row.querySelector('input[name="player_name[]"]');
        let birthdateInput = row.querySelector('input[name="player_birthdate[]"]');
        let genderSelect = row.querySelector('select[name="gender[]"]');
        let jerseyInput = row.querySelector('input[name="jersey_number[]"]');

        if (nameInput && nameInput.value.trim()) {
            playerCount++;
            const normalizedName = nameInput.value.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
            if (!playerNames.has(normalizedName)) playerNames.set(normalizedName, []);
            playerNames.get(normalizedName).push(index + 1);
        }

        if (jerseyInput && jerseyInput.value !== '') {
            const normalizedJersey = String(Number(jerseyInput.value));
            if (!jerseyNumbers.has(normalizedJersey)) jerseyNumbers.set(normalizedJersey, []);
            jerseyNumbers.get(normalizedJersey).push(index + 1);
        }

        if (birthdateInput && birthdateInput.value) {
            const birthdate = new Date(`${birthdateInput.value}T00:00:00`);
            const reference = new Date(`${REG_REQUIREMENTS.start_date || REG_TODAY}T00:00:00`);
            let age = reference.getFullYear() - birthdate.getFullYear();
            const beforeBirthday = reference.getMonth() < birthdate.getMonth() || (reference.getMonth() === birthdate.getMonth() && reference.getDate() < birthdate.getDate());
            if (beforeBirthday) age--;
            if (REG_REQUIREMENTS.min_age && age < REG_REQUIREMENTS.min_age) {
                ageIssues.push(`Player ${index + 1}: Age ${age} is below minimum (${REG_REQUIREMENTS.min_age})`);
                if (birthdateInput) birthdateInput.classList.add('player-field-invalid');
            }
            if (REG_REQUIREMENTS.max_age && age > REG_REQUIREMENTS.max_age) {
                ageIssues.push(`Player ${index + 1}: Age ${age} is above maximum (${REG_REQUIREMENTS.max_age})`);
                if (birthdateInput) birthdateInput.classList.add('player-field-invalid');
            }
        }

        if (genderSelect && genderSelect.value) {
            if (genderSelect.value === 'Male') maleCount++;
            if (genderSelect.value === 'Female') femaleCount++;
        }
    });

    // Check player count
    if (playerCount < REG_MIN_ROWS) {
        issues.push(`Only ${playerCount} player(s) entered. Minimum ${REG_MIN_ROWS} required.`);
    }
    if (REG_REQUIREMENTS.max_players && playerCount > REG_REQUIREMENTS.max_players) {
        issues.push(`${playerCount} player(s) entered. Maximum ${REG_REQUIREMENTS.max_players} allowed.`);
    }

    // Highlight duplicate names
    playerNames.forEach((rowNumbers, name) => {
        if (rowNumbers.length > 1) {
            issues.push(`Duplicate player name in rows ${rowNumbers.join(', ')}.`);
            rowNumbers.forEach(rowNum => {
                const nameInput = rows[rowNum - 1]?.querySelector('input[name="player_name[]"]');
                if (nameInput) nameInput.classList.add('player-field-invalid');
            });
        }
    });

    // Highlight duplicate jersey numbers
    jerseyNumbers.forEach((rowNumbers, jerseyNumber) => {
        if (rowNumbers.length > 1) {
            issues.push(`Jersey number ${jerseyNumber} is used in rows ${rowNumbers.join(', ')}.`);
            rowNumbers.forEach(rowNum => {
                const jerseyInput = rows[rowNum - 1]?.querySelector('input[name="jersey_number[]"]');
                if (jerseyInput) jerseyInput.classList.add('player-field-invalid');
            });
        }
    });

    // Check gender requirements
    if (REG_REQUIREMENTS.min_male_players && maleCount < REG_REQUIREMENTS.min_male_players) {
        issues.push(`Only ${maleCount} male player(s). Minimum ${REG_REQUIREMENTS.min_male_players} required.`);
    }
    if (REG_REQUIREMENTS.min_female_players && femaleCount < REG_REQUIREMENTS.min_female_players) {
        issues.push(`Only ${femaleCount} female player(s). Minimum ${REG_REQUIREMENTS.min_female_players} required.`);
    }

    // Highlight gender inputs that violate category restrictions
    if (REG_REQUIREMENTS.category === 'Male' && femaleCount > 0) {
        issues.push('This is a Male-only league. Remove female players.');
        rows.forEach(row => {
            const g = row.querySelector('select[name="gender[]"]');
            if (g && g.value === 'Female') g.classList.add('player-field-invalid');
        });
    }
    if (REG_REQUIREMENTS.category === 'Female' && maleCount > 0) {
        issues.push('This is a Female-only league. Remove male players.');
        rows.forEach(row => {
            const g = row.querySelector('select[name="gender[]"]');
            if (g && g.value === 'Male') g.classList.add('player-field-invalid');
        });
    }

    // Add age issues
    issues = issues.concat(ageIssues);

    // Required fields, documents included. Listed only when the caller asks
    // (i.e. right before submit): during normal editing the roster-count rules
    // already explain the state, and a full list of empty 5-player fields would
    // bury it on the first render.
    if (includeRequired) {
        issues = issues.concat(collectRequiredFieldIssues());
    }

    // Display validation summary
    let summaryDiv = document.getElementById("validationSummary");
    let validationList = document.getElementById("validationList");

    if (issues.length > 0) {
        validationList.innerHTML = issues.map(issue => `<li>${issue}</li>`).join('');
        summaryDiv.classList.remove('d-none');
    } else {
        summaryDiv.classList.add('d-none');
    }
}

/* -------------------------------------------------------------------------
   Draft autosave (localStorage, per representative + league)
   File inputs cannot be repopulated by script, so only their names are kept
   and the form shows a "re-attach these files" checklist on resume.
   ------------------------------------------------------------------------- */
function initialRowCount() {
    const maximumPlayers = Number(REG_REQUIREMENTS.max_players || 0);
    return maximumPlayers > 0 ? Math.min(REG_MIN_ROWS, maximumPlayers) : REG_MIN_ROWS;
}

function draftKey() {
    const leagueId = String(REG_CFG.leagueId || '');
    if (!leagueId || leagueId === '0') return null;
    return `${REG_DRAFT_PREFIX}:${REG_CFG.userId || 'guest'}:${leagueId}`;
}

function setDraftStatus(message, isSaved) {
    const el = document.getElementById('draftStatus');
    if (!el) return;
    el.textContent = message;
    el.classList.toggle('is-empty', !isSaved);
    const discard = document.getElementById('discardDraftBtn');
    if (discard) discard.classList.toggle('d-none', !isSaved);
}

function requirementsFingerprint() {
    return JSON.stringify({
        min: Number(REG_REQUIREMENTS.min_players || 0),
        max: Number(REG_REQUIREMENTS.max_players || 0),
        category: String(REG_REQUIREMENTS.category || ''),
        docs: REG_PLAYER_DOCUMENTS.map(doc => String(doc.id)).sort()
    });
}

function isRegistrationDirty() {
    if (!regForm) return false;
    const teamName = document.getElementById('team_name');
    if (teamName && teamName.value.trim()) return true;
    if (Array.from(regForm.querySelectorAll('input[type="file"]')).some(input => input.files?.length)) return true;
    return Array.from(document.querySelectorAll('#playerBody tr')).some(row =>
        (row.querySelector('input[name="player_name[]"]')?.value || '').trim() ||
        (row.querySelector('input[name="player_birthdate[]"]')?.value || '') ||
        (row.querySelector('input[name="player_role[]"]')?.value || '').trim() ||
        (row.querySelector('input[name="jersey_number[]"]')?.value || '') !== '' ||
        (row.querySelector('select[name="gender[]"]')?.value || '')
    );
}

function collectFileSlots() {
    const slots = [];

    const teamImage = document.getElementById('team_image');
    if (teamImage && teamImage.files?.length) {
        slots.push({ slot: 'team_image', label: 'Team image', name: teamImage.files[0].name });
    }

    regForm.querySelectorAll('input[type="file"][data-document-scope="team"]').forEach(input => {
        if (!input.files?.length) return;
        const label = input.closest('.document-field-block')?.querySelector('label')?.textContent.trim() || 'Team document';
        slots.push({ slot: `team_document:${input.dataset.documentRequirement}`, label, name: input.files[0].name });
    });

    document.querySelectorAll('#playerBody tr').forEach((row, rowIndex) => {
        row.querySelectorAll('input[type="file"]').forEach(input => {
            if (!input.files?.length) return;
            const requirementId = input.dataset.documentRequirement || 'legacy';
            const label = input.closest('.document-field-block')?.querySelector('label')?.textContent.trim() || 'Player document';
            slots.push({
                slot: `player:${rowIndex}:doc:${requirementId}`,
                label: `Player ${rowIndex + 1}: ${label}`,
                name: input.files[0].name
            });
        });
    });

    return slots;
}

function collectDraft() {
    const teamName = document.getElementById('team_name');
    return {
        v: REG_DRAFT_VERSION,
        savedAt: Date.now(),
        leagueId: REG_CFG.leagueId,
        leagueName: REG_CFG.leagueName || '',
        fingerprint: requirementsFingerprint(),
        teamName: teamName ? teamName.value : '',
        players: Array.from(document.querySelectorAll('#playerBody tr')).map(row => ({
            name: row.querySelector('input[name="player_name[]"]')?.value || '',
            birthdate: row.querySelector('input[name="player_birthdate[]"]')?.value || '',
            role: row.querySelector('input[name="player_role[]"]')?.value || '',
            jersey: row.querySelector('input[name="jersey_number[]"]')?.value || '',
            gender: row.querySelector('select[name="gender[]"]')?.value || ''
        })),
        files: collectFileSlots()
    };
}

function saveDraftNow() {
    const key = draftKey();
    if (!key || regSubmitted || !isRegistrationDirty()) return;
    try {
        localStorage.setItem(key, JSON.stringify(collectDraft()));
        setDraftStatus('Draft saved ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }), true);
    } catch (error) {
        console.warn('Draft save failed', error);
    }
}

function scheduleDraftSave() {
    if (regSubmitted) return;
    clearTimeout(regDraftTimer);
    regDraftTimer = setTimeout(saveDraftNow, 600);
}

function clearDraft() {
    const key = draftKey();
    if (key) {
        try { localStorage.removeItem(key); } catch (error) { /* ignore */ }
    }
    deleteFileBlobs();
    setDraftStatus('Draft autosave is on — your entries are saved on this device.', false);
}

function readDraft() {
    const key = draftKey();
    if (!key) return null;
    try {
        const raw = localStorage.getItem(key);
        if (!raw) return null;
        const draft = JSON.parse(raw);
        if (!draft || draft.v !== REG_DRAFT_VERSION) {
            localStorage.removeItem(key);
            return null;
        }
        if (Date.now() - Number(draft.savedAt || 0) > REG_DRAFT_TTL_DAYS * 86400000) {
            localStorage.removeItem(key);
            return null;
        }
        return draft;
    } catch (error) {
        return null;
    }
}

/* -------------------------------------------------------------------------
   File blobs (IndexedDB)
   -------------------------------------------------------------------------
   localStorage cannot hold Blobs, so the selected documents are kept in
   IndexedDB and re-attached on resume through DataTransfer. If the browser
   refuses either step (or the draft is too large) everything degrades to the
   "re-attach these files" checklist, which is what shipped before.
   ------------------------------------------------------------------------- */
const REG_DB_NAME = 'lms_team_reg_drafts';
const REG_DB_VERSION = 1;
const REG_FILE_STORE = 'files';
const REG_FILE_LIMIT_BYTES = 25 * 1024 * 1024; // per draft, protects the quota

let regDbPromise = null;

function openDraftDb() {
    if (!('indexedDB' in window)) return Promise.resolve(null);
    if (regDbPromise) return regDbPromise;

    regDbPromise = new Promise(resolve => {
        let request;
        try {
            request = window.indexedDB.open(REG_DB_NAME, REG_DB_VERSION);
        } catch (error) {
            resolve(null);
            return;
        }
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(REG_FILE_STORE)) {
                const store = db.createObjectStore(REG_FILE_STORE, { keyPath: 'key' });
                store.createIndex('draftKey', 'draftKey', { unique: false });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => resolve(null);
        request.onblocked = () => resolve(null);
    });

    return regDbPromise;
}

function withFileStore(mode, work) {
    return openDraftDb().then(db => {
        if (!db) return null;
        return new Promise(resolve => {
            let transaction;
            try {
                transaction = db.transaction(REG_FILE_STORE, mode);
            } catch (error) {
                resolve(null);
                return;
            }
            try {
                work(transaction.objectStore(REG_FILE_STORE));
            } catch (error) {
                resolve(null);
                return;
            }
            transaction.oncomplete = () => resolve(true);
            transaction.onerror = () => resolve(null);
            transaction.onabort = () => resolve(null);
        });
    });
}

/** Reverse of resolveDraftFileInput(): the stable slot id for a live input. */
function slotForInput(input) {
    if (!input) return null;
    if (input.id === 'team_image') return 'team_image';
    const scope = input.dataset ? input.dataset.documentScope : '';
    if (scope === 'team') return `team_document:${input.dataset.documentRequirement}`;
    if (scope === 'player' || scope === 'legacy_player') {
        const row = input.closest('tr');
        if (!row) return null;
        const index = Array.prototype.indexOf.call(document.querySelectorAll('#playerBody tr'), row);
        if (index < 0) return null;
        const requirementId = scope === 'legacy_player' ? 'legacy' : input.dataset.documentRequirement;
        return `player:${index}:doc:${requirementId}`;
    }
    return null;
}

function draftFilesTotalBytes() {
    if (!regForm) return 0;
    let total = 0;
    regForm.querySelectorAll('input[type="file"]').forEach(input => {
        const file = input.files && input.files[0];
        if (file) total += Number(file.size || 0);
    });
    return total;
}

function saveFileRecords(records) {
    const key = draftKey();
    if (!key || !records.length) return Promise.resolve();
    return withFileStore('readwrite', store => {
        records.forEach(record => {
            const file = record.file;
            if (!file) return;
            store.put({
                key: key + '::' + record.slot,
                draftKey: key,
                slot: record.slot,
                name: file.name,
                type: file.type,
                lastModified: file.lastModified,
                savedAt: Date.now(),
                blob: file
            });
        });
    });
}

function deleteFileBlobs() {
    const key = draftKey();
    if (!key) return Promise.resolve();
    const prefix = key + '::';
    return withFileStore('readwrite', store => {
        const request = store.openCursor();
        request.onsuccess = () => {
            const cursor = request.result;
            if (!cursor) return;
            if (String(cursor.primaryKey || '').indexOf(prefix) === 0) cursor.delete();
            cursor.continue();
        };
    });
}

function loadFileBlobs() {
    const key = draftKey();
    if (!key) return Promise.resolve([]);
    const records = [];
    return withFileStore('readonly', store => {
        const request = store.openCursor();
        request.onsuccess = () => {
            const cursor = request.result;
            if (!cursor) return;
            const value = cursor.value;
            if (value && value.draftKey === key) records.push(value);
            cursor.continue();
        };
    }).then(() => records);
}

/** Persist (or remove) the blob behind one file input. */
function syncFileBlob(input) {
    const key = draftKey();
    const slot = slotForInput(input);
    if (!key || !slot) return Promise.resolve();

    const file = input.files && input.files[0];
    if (!file) {
        return withFileStore('readwrite', store => { store.delete(key + '::' + slot); });
    }
    if (draftFilesTotalBytes() > REG_FILE_LIMIT_BYTES) {
        setDraftStatus('Draft saved — the files are too large to keep on this device, so re-attach them when you resume.', true);
        return deleteFileBlobs();
    }
    return saveFileRecords([{ slot, file }]);
}

/** Row indices can move when a player is removed → re-key every stored blob. */
function rebuildFileBlobs() {
    if (!regForm) return Promise.resolve();
    if (draftFilesTotalBytes() > REG_FILE_LIMIT_BYTES) return deleteFileBlobs();

    const records = [];
    regForm.querySelectorAll('input[type="file"]').forEach(input => {
        const file = input.files && input.files[0];
        const slot = slotForInput(input);
        if (file && slot) records.push({ slot, file });
    });

    return deleteFileBlobs().then(() => saveFileRecords(records));
}

function purgeExpiredFileBlobs() {
    const cutoff = Date.now() - REG_DRAFT_TTL_DAYS * 86400000;
    return withFileStore('readwrite', store => {
        const request = store.openCursor();
        request.onsuccess = () => {
            const cursor = request.result;
            if (!cursor) return;
            const savedAt = Number((cursor.value && cursor.value.savedAt) || 0);
            if (!savedAt || savedAt < cutoff) cursor.delete();
            cursor.continue();
        };
    });
}

function resolveDraftFileInput(slot) {
    if (!regForm || typeof slot !== 'string') return null;
    if (slot === 'team_image') return document.getElementById('team_image');

    const teamDocument = /^team_document:(\d+)$/.exec(slot);
    if (teamDocument) {
        return regForm.querySelector(`input[type="file"][data-document-scope="team"][data-document-requirement="${teamDocument[1]}"]`);
    }

    const playerDocument = /^player:(\d+):doc:(.+)$/.exec(slot);
    if (playerDocument) {
        const row = document.querySelectorAll('#playerBody tr')[Number(playerDocument[1])];
        if (!row) return null;
        const requirementId = playerDocument[2];
        if (requirementId === 'legacy') return row.querySelector('input[data-document-scope="legacy_player"]');
        return row.querySelector(`input[data-document-scope="player"][data-document-requirement="${requirementId}"]`);
    }

    return null;
}

function clearDraftFileNote(input) {
    if (!input || !input.parentElement) return;
    input.classList.remove('draft-file-missing');
    input.parentElement.querySelectorAll(':scope > .draft-file-note').forEach(note => note.remove());
}

function markMissingDraftFile(entry) {
    const input = resolveDraftFileInput(entry.slot);
    if (!input || input.files?.length) return;
    input.classList.add('draft-file-missing');
    if (input.parentElement.querySelector(':scope > .draft-file-note')) return;
    const note = document.createElement('small');
    note.className = 'draft-file-note';
    note.textContent = `Previously attached: ${entry.name || 'attachment'} — please re-select this file.`;
    input.insertAdjacentElement('afterend', note);
}

/** Re-attach one stored blob to its input. Returns false if the browser refuses. */
function attachFileToInput(input, record) {
    if (!input || !record || !record.blob) return false;
    if (typeof DataTransfer === 'undefined') return false;
    try {
        const blob = record.blob;
        const file = (typeof File === 'function')
            ? new File([blob], record.name || 'document', {
                type: record.type || blob.type || '',
                lastModified: Number(record.lastModified) || Date.now()
            })
            : blob;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        clearDraftFileNote(input);
        input.classList.remove('document-quality-failed');
        input.removeAttribute('aria-invalid');
        return input.files.length > 0;
    } catch (error) {
        return false;
    }
}

function restoreDraftFiles(records) {
    (Array.isArray(records) ? records : []).forEach(record => {
        const input = resolveDraftFileInput(record.slot);
        if (!input || (input.files && input.files.length)) return;
        attachFileToInput(input, record);
    });
}

/** Rebuilds the banner list, marking what came back automatically and what did not. */
function updateDraftChecklist(fileEntries) {
    const checklist = document.getElementById('draftFileChecklist');
    const list = document.getElementById('draftFileChecklistList');
    const title = document.getElementById('draftFileChecklistTitle');
    if (!checklist || !list) return;

    list.innerHTML = '';
    let missing = 0;

    (fileEntries || []).forEach(entry => {
        const input = resolveDraftFileInput(entry.slot);
        const attached = Boolean(input && input.files && input.files.length);
        const item = document.createElement('li');
        const label = `${entry.label || 'Document'} — ${entry.name || 'attachment'}`;

        if (attached) {
            item.className = 'draft-file-restored';
            item.textContent = `${label} (restored)`;
        } else {
            item.textContent = `${label} (re-attach required)`;
            missing++;
            markMissingDraftFile(entry);
        }
        list.appendChild(item);
    });

    if (title) {
        title.textContent = missing > 0
            ? 'Re-attach these files before submitting:'
            : 'Files restored from your draft:';
    }
    checklist.classList.toggle('d-none', list.children.length === 0);
}

function renderDraftBanner(draft, options) {
    const banner = document.getElementById('draftRestoreBanner');
    const text = document.getElementById('draftRestoreText');
    const checklist = document.getElementById('draftFileChecklist');
    const list = document.getElementById('draftFileChecklistList');
    if (!banner || !text || !checklist || !list) return;

    const savedAt = new Date(Number(draft.savedAt || Date.now())).toLocaleString();
    const notes = [`Draft saved ${savedAt}.`];
    if (options.clamped) notes.push('Some player rows were dropped because the league roster limit changed.');
    if (options.configChanged) notes.push("This league's requirements changed since the draft was saved — please review every field.");
    text.textContent = notes.join(' ');

    list.innerHTML = '';
    const files = Array.isArray(draft.files) ? draft.files : [];
    updateDraftChecklist(files);
    banner.classList.remove('d-none');
}

function applyDraft(draft) {
    const maximumPlayers = Number(REG_REQUIREMENTS.max_players || 0);
    let players = Array.isArray(draft.players) ? draft.players : [];
    let clamped = false;
    if (maximumPlayers > 0 && players.length > maximumPlayers) {
        players = players.slice(0, maximumPlayers);
        clamped = true;
    }
    const configChanged = Boolean(draft.fingerprint) && draft.fingerprint !== requirementsFingerprint();

    const teamName = document.getElementById('team_name');
    if (teamName) teamName.value = draft.teamName || '';

    initRoster(Math.max(REG_MIN_ROWS, players.length));

    const rows = document.querySelectorAll('#playerBody tr');
    players.forEach((player, index) => {
        const row = rows[index];
        if (!row) return;
        const values = [
            ['player_name[]', player.name],
            ['player_birthdate[]', player.birthdate],
            ['player_role[]', player.role],
            ['jersey_number[]', player.jersey],
            ['gender[]', player.gender]
        ];
        values.forEach(([name, value]) => {
            if (!value) return;
            const field = row.querySelector(`[name="${name}"]`);
            if (field) field.value = value;
        });
    });

    updatePlayerRowCount();
    validateForm();
    renderDraftBanner(draft, { clamped, configChanged });
    setDraftStatus('Draft saved ' + new Date(Number(draft.savedAt || Date.now())).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }), true);
}

function restoreDraft() {
    const draft = readDraft();
    if (!draft) return;

    const savedAt = new Date(Number(draft.savedAt || Date.now())).toLocaleString();
    Swal.fire({
        icon: 'question',
        title: 'Resume your registration?',
        html: `We found an unfinished draft for this league, saved <strong>${savedAt}</strong>.`,
        showDenyButton: true,
        confirmButtonText: 'Resume draft',
        denyButtonText: 'Start over',
        confirmButtonColor: '#2f6fed',
        denyButtonColor: '#6c757d'
    }).then(result => {
        if (result.isConfirmed) {
            applyDraft(draft);
            // Documents live in IndexedDB; re-attach them once the rows exist.
            loadFileBlobs()
                .then(records => {
                    restoreDraftFiles(records);
                    updateDraftChecklist(Array.isArray(draft.files) ? draft.files : []);
                })
                .catch(() => { /* the checklist already shows what is missing */ });
        } else if (result.isDenied) {
            discardDraft();
        }
    });
}

function discardDraft() {
    clearDraft();
    if (!regForm) return;

    const teamName = document.getElementById('team_name');
    if (teamName) teamName.value = '';
    regForm.querySelectorAll('input[type="file"]').forEach(input => {
        input.value = '';
        clearDraftFileNote(input);
        input.classList.remove('document-quality-failed');
        input.removeAttribute('aria-invalid');
        input.removeAttribute('data-document-rejected');
    });
    initRoster(initialRowCount());
    const banner = document.getElementById('draftRestoreBanner');
    if (banner) banner.classList.add('d-none');
    validateForm();
}

function confirmDiscardDraft() {
    Swal.fire({
        icon: 'warning',
        title: 'Discard this draft?',
        text: 'Your team details, roster and document selections will be cleared. This cannot be undone.',
        showCancelButton: true,
        confirmButtonText: 'Discard draft',
        cancelButtonText: 'Keep editing',
        confirmButtonColor: '#b02a37',
        reverseButtons: true
    }).then(result => {
        if (result.isConfirmed) discardDraft();
    });
}

/* -------------------------------------------------------------------------
   Submit
   ------------------------------------------------------------------------- */

/**
 * Blocking progress dialog shown while the upload is quality-checked.
 *
 * It has no OK/Cancel button on purpose. The check runs on the server and the
 * request cannot be stopped from the browser at that point, so an "OK" button
 * only hid the overlay while validation kept running — which made the check
 * look skippable. window.LmsDialog (assets/js/lms-dialog.js) is the shared
 * implementation used everywhere else in the app; the inline fallback keeps
 * the dialog blocking if that script did not load.
 */
function showRegistrationProgress() {
    if (window.LmsDialog && typeof LmsDialog.loading === 'function') {
        LmsDialog.loading('Processing Registration', 'Validating documents…');
        return;
    }

    Swal.fire({
        title: 'Processing Registration',
        html: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3">Validating documents…</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        allowEnterKey: false,
        showConfirmButton: false,
        showCancelButton: false
    });
}

/** Dismiss the blocking progress dialog before a result dialog is shown. */
function closeRegistrationProgress() {
    if (window.LmsDialog && typeof LmsDialog.close === 'function') {
        LmsDialog.close();
        return;
    }
    if (window.Swal) Swal.close();
}

if (regForm) {
    regForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitButton = document.getElementById('registerTeamSubmit');
        if (submitButton.disabled || regSubmitting) return;

        // Refresh the summary from the live form (required fields and documents
        // included) so the gate below can never be stale.
        validateForm(true);

        const summaryDiv = document.getElementById("validationSummary");
        if (summaryDiv && !summaryDiv.classList.contains('d-none')) {
            const issueCount = document.querySelectorAll('#validationList li').length;
            const firstInvalid = regForm.querySelector('.player-field-invalid, input:invalid');
            summaryDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (firstInvalid) {
                window.setTimeout(() => {
                    if (typeof firstInvalid.focus === 'function') {
                        firstInvalid.focus({ preventScroll: true });
                    }
                }, 320);
            }
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Check the highlighted fields',
                    text: 'Please fix the ' + (issueCount || 'highlighted') + ' item(s) listed above, then submit again.',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Please fix the highlighted fields before submitting.');
            }
            return;
        }

        // A document the checker already rejected must be replaced first.
        const rejectedDocuments = rejectedDocumentInputs();
        if (rejectedDocuments.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Replace the rejected document',
                text: 'This document was rejected as unreadable or severely blurry. Choose a clearer file before submitting again.',
                confirmButtonText: 'OK'
            }).then(() => {
                rejectedDocuments[0].scrollIntoView({behavior:'smooth', block:'center'});
                window.setTimeout(() => rejectedDocuments[0].focus({preventScroll:true}), 250);
            });
            return;
        }

        const submitButtonHtml = submitButton.innerHTML;
        regSubmitting = true;
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Submitting...';

        // Show loading indicator (frame + typography come from lms-modal.css)
        showRegistrationProgress();

        // Downscale the selected photos before they leave the device (drafts and
        // restored files can still hold full-resolution originals).
        await optimiseSelectedImages();

        const formData = new FormData(regForm);

        // Heroku (and most proxies) cut a request off long before the browser
        // gives up, so bound the wait: a hung upload otherwise looks like a dead
        // button. The draft is saved, so retrying after the timeout is cheap.
        const controller = new AbortController();
        const abortTimer = window.setTimeout(() => controller.abort(), 180000);

        fetch(REG_CFG.api, {
            method: 'POST',
            body: formData,
            signal: controller.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            const raw = await response.text();
            let data = null;
            if (raw) {
                try {
                    data = JSON.parse(raw);
                } catch (parseError) {
                    data = null;
                }
            }
            if (!data || typeof data !== 'object') {
                let message;
                if (response.status === 413) {
                    message = 'The uploaded files are too large for the server. Reduce the file sizes and try again.';
                } else if (response.status === 503 || response.status === 504) {
                    // The hosted proxy stopped the request (Heroku returns 503
                    // after ~30 s, usually while a large upload is still being
                    // received). The draft on this device is intact.
                    message = 'The server took too long to accept the upload and stopped the request (HTTP ' + response.status + '). '
                        + 'Your draft is saved on this device. Check your connection and try again — smaller photos and fewer documents upload faster.';
                } else {
                    message = `The server returned an unexpected response (HTTP ${response.status}). Please try again.`;
                }
                throw new Error(message);
            }
            if (!response.ok && !data.msg) {
                data.msg = `Registration failed (HTTP ${response.status}). Please try again.`;
            }
            return data;
        })
        .then(data => {
            if (data.status === 201) {
                regSubmitted = true;
                clearDraft();
                closeRegistrationProgress();
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.msg,
                    confirmButtonText: 'View my applications'
                }).then(() => {
                    window.location.href = REG_CFG.redirect || 'league.php';
                });
                return;
            }

            if (data.status === 401 || data.status === 419) {
                saveDraftNow();
                closeRegistrationProgress();
                Swal.fire({
                    icon: 'warning',
                    title: 'Session expired',
                    text: 'Your session ended before the registration was submitted. Your draft is saved on this device — sign in again to continue.',
                    confirmButtonText: 'Sign in again'
                }).then(() => {
                    window.location.href = REG_CFG.loginUrl || '../../login.php';
                });
                return;
            }

            if (data.status === 403) {
                closeRegistrationProgress();
                const state = String(data.state || '');
                const closed = state === 'closed' || state === 'upcoming';
                Swal.fire({
                    icon: closed ? 'info' : 'error',
                    title: closed ? (state === 'upcoming' ? 'Registration not open yet' : 'Registration closed') : 'Not allowed',
                    text: data.msg || 'You are not allowed to register for this league.',
                    confirmButtonText: closed ? 'Back to league' : 'OK'
                }).then(() => {
                    if (closed && REG_CFG.backUrl) window.location.href = REG_CFG.backUrl;
                });
                return;
            }

            // The request body exceeded post_max_size: PHP never saw the fields,
            // so the answer is a dedicated 413 instead of a bogus 401/419.
            if (data.status === 413) {
                closeRegistrationProgress();
                Swal.fire({
                    icon: 'error',
                    title: 'Files too large',
                    text: data.msg || 'The upload is too large for the server. Remove a few documents or take smaller photos, then try again.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            const fieldErrors = Array.isArray(data.field_errors) ? data.field_errors : [];
            showDocumentQualityErrors(fieldErrors);
            if (!fieldErrors.length) showDocumentErrorFallback(data.msg);
            closeRegistrationProgress();
            Swal.fire({
                icon: 'error',
                title: 'Registration Failed',
                text: data.msg || 'Registration failed. Please try again.',
                confirmButtonText: 'OK'
            }).then(() => {
                if (fieldErrors.length) showDocumentQualityErrors(fieldErrors);
                else showDocumentErrorFallback(data.msg);
            });
        })
        .catch(error => {
            console.error('Registration error:', error);
            closeRegistrationProgress();

            // fetch() rejects with a bare TypeError ("Failed to fetch" /
            // "Load failed") when the connection dies mid-upload — the browser
            // gives the user nothing actionable in that message. Translate it.
            const timedOut = !!error && error.name === 'AbortError';
            const network  = !timedOut && /failed to fetch|load failed|networkerror/i.test(String((error && error.message) || ''));
            let message;
            if (timedOut) {
                message = 'The upload took too long and was stopped. Your draft is saved on this device — check your connection and submit again.';
            } else if (network) {
                message = 'The connection dropped while sending your registration. Your draft is saved on this device — check your mobile data / Wi-Fi and try again (smaller photos upload more reliably).';
            } else {
                message = (error && error.message) || 'An error occurred during registration. Please try again.';
            }

            Swal.fire({
                icon: 'error',
                title: 'Registration Failed',
                text: message,
                confirmButtonText: 'OK'
            });
        })
        .finally(() => {
            window.clearTimeout(abortTimer);
            regSubmitting = false;
            submitButton.disabled = false;
            submitButton.innerHTML = submitButtonHtml;
        });
    });
}

/* -------------------------------------------------------------------------
   Page bootstrap
   ------------------------------------------------------------------------- */
function handleBeforeUnload(event) {
    if (regSubmitted || regExiting || !isRegistrationDirty()) return;
    saveDraftNow();
    event.preventDefault();
    event.returnValue = '';
}

function bootstrapRegistrationPage() {
    if (!regForm) return;

    initRoster(initialRowCount());
    validateForm();
    restoreDraft();
    purgeExpiredFileBlobs();

    regForm.addEventListener('input', scheduleDraftSave);

    const discardButton = document.getElementById('discardDraftBtn');
    if (discardButton) discardButton.addEventListener('click', confirmDiscardDraft);

    const saveExitButton = document.getElementById('saveDraftExitBtn');
    if (saveExitButton) {
        saveExitButton.addEventListener('click', function () {
            regExiting = true;
            saveDraftNow();
            window.location.href = REG_CFG.backUrl || 'browse_leagues.php';
        });
    }

    window.addEventListener('beforeunload', handleBeforeUnload);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapRegistrationPage);
} else {
    bootstrapRegistrationPage();
}
