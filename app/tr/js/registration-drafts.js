/**
 * "Unfinished registrations" panel.
 *
 * Fills app/tr/includes/registration-drafts-panel.php from the drafts this
 * device holds. It reads exactly what app/tr/js/register-team.js writes:
 *
 *   localStorage  lms_team_reg_draft:<userId>:<leagueId>   → draft JSON
 *   IndexedDB     lms_team_reg_drafts / store "files"      → keys "<draftKey>::<slot>"
 *
 * Keep the IndexedDB schema below in sync with register-team.js.
 */
(function () {
    'use strict';

    var CFG = window.LMS_REGISTRATION_DRAFTS || {};
    var DRAFT_PREFIX = 'lms_team_reg_draft:';
    var DRAFT_VERSION = 1;
    var DRAFT_TTL_DAYS = 7;
    var DB_NAME = 'lms_team_reg_drafts';
    var DB_VERSION = 1;
    var FILE_STORE = 'files';

    var panel = document.getElementById(CFG.panelId || 'trDraftPanel');
    var list = document.getElementById(CFG.listId || 'trDraftList');
    if (!panel || !list || !CFG.userId) return;

    function prefix() {
        return DRAFT_PREFIX + CFG.userId + ':';
    }

    function safeRemove(key) {
        try { localStorage.removeItem(key); } catch (error) { /* ignore */ }
    }

    function timeAgo(timestamp) {
        var minutes = Math.floor(Math.max(0, Date.now() - timestamp) / 60000);
        if (minutes < 1) return 'saved just now';
        if (minutes < 60) return 'saved ' + minutes + (minutes === 1 ? ' minute ago' : ' minutes ago');
        var hours = Math.floor(minutes / 60);
        if (hours < 24) return 'saved ' + hours + (hours === 1 ? ' hour ago' : ' hours ago');
        var days = Math.floor(hours / 24);
        return 'saved ' + days + (days === 1 ? ' day ago' : ' days ago');
    }

    /** Every usable draft for this representative, newest first. */
    function readDrafts() {
        var start = prefix();
        var keys = [];
        var drafts = [];

        try {
            for (var i = 0; i < localStorage.length; i++) {
                keys.push(localStorage.key(i));
            }
        } catch (error) {
            return drafts;
        }

        keys.forEach(function (key) {
            if (!key || key.indexOf(start) !== 0) return;

            var raw = null;
            try { raw = localStorage.getItem(key); } catch (error) { return; }
            if (!raw) return;

            var draft = null;
            try { draft = JSON.parse(raw); } catch (error) { draft = null; }

            // Version or shape changed → the draft is unusable.
            if (!draft || draft.v !== DRAFT_VERSION) { safeRemove(key); return; }

            var savedAt = Number(draft.savedAt || 0);
            if (!savedAt || Date.now() - savedAt > DRAFT_TTL_DAYS * 86400000) { safeRemove(key); return; }

            var players = Array.isArray(draft.players) ? draft.players : [];
            var filledPlayers = players.filter(function (player) {
                return player && String(player.name || '').trim() !== '';
            }).length;

            drafts.push({
                key: key,
                leagueId: String(draft.leagueId || key.slice(start.length)),
                leagueName: String(draft.leagueName || ''),
                savedAt: savedAt,
                players: filledPlayers
            });
        });

        drafts.sort(function (a, b) { return b.savedAt - a.savedAt; });
        return drafts;
    }

    /** Remove the IndexedDB blobs that belong to one draft key. */
    function deleteDraftFiles(draftKey) {
        return new Promise(function (resolve) {
            if (!('indexedDB' in window)) { resolve(); return; }

            var request;
            try {
                request = window.indexedDB.open(DB_NAME, DB_VERSION);
            } catch (error) {
                resolve();
                return;
            }

            request.onupgradeneeded = function () {
                var db = request.result;
                if (!db.objectStoreNames.contains(FILE_STORE)) {
                    var store = db.createObjectStore(FILE_STORE, { keyPath: 'key' });
                    store.createIndex('draftKey', 'draftKey', { unique: false });
                }
            };
            request.onerror = function () { resolve(); };
            request.onsuccess = function () {
                var db = request.result;
                var transaction;
                try {
                    transaction = db.transaction(FILE_STORE, 'readwrite');
                } catch (error) {
                    resolve();
                    return;
                }

                var filePrefix = draftKey + '::';
                var cursor = transaction.objectStore(FILE_STORE).openCursor();
                cursor.onsuccess = function () {
                    var current = cursor.result;
                    if (!current) return;
                    if (String(current.primaryKey || '').indexOf(filePrefix) === 0) current.delete();
                    current.continue();
                };
                transaction.oncomplete = function () { resolve(); };
                transaction.onerror = function () { resolve(); };
                transaction.onabort = function () { resolve(); };
            };
        });
    }

    function buildRow(draft) {
        var item = document.createElement('li');
        item.className = 'tr-draft';

        var icon = document.createElement('span');
        icon.className = 'tr-drafts-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = '<i class="bi bi-file-earmark-text"></i>';

        var copy = document.createElement('div');
        copy.className = 'tr-draft-copy';

        var resumeHref = (CFG.resumeUrl || 'register_team.php') + '?id=' + encodeURIComponent(draft.leagueId);

        var link = document.createElement('a');
        link.className = 'tr-draft-name';
        link.href = resumeHref;
        link.textContent = draft.leagueName || ('League #' + draft.leagueId);
        link.title = 'Resume this registration';

        var meta = document.createElement('span');
        meta.className = 'tr-draft-meta';
        meta.textContent = timeAgo(draft.savedAt)
            + ' · ' + (draft.players === 1 ? '1 player' : draft.players + ' players')
            + ' · ' + new Date(draft.savedAt).toLocaleString();

        copy.appendChild(link);
        copy.appendChild(meta);

        var actions = document.createElement('div');
        actions.className = 'tr-draft-actions';

        var resume = document.createElement('a');
        resume.className = 'tr-draft-resume';
        resume.href = resumeHref;
        resume.innerHTML = 'Resume <i class="bi bi-arrow-right" aria-hidden="true"></i>';

        var discard = document.createElement('button');
        discard.type = 'button';
        discard.className = 'tr-draft-discard';
        discard.textContent = 'Discard';
        discard.addEventListener('click', function () { confirmDiscard(draft); });

        actions.appendChild(resume);
        actions.appendChild(discard);

        item.appendChild(icon);
        item.appendChild(copy);
        item.appendChild(actions);
        return item;
    }

    function render() {
        var drafts = readDrafts();
        list.innerHTML = '';

        drafts.forEach(function (draft) {
            list.appendChild(buildRow(draft));
        });

        panel.hidden = drafts.length === 0;
    }

    function finishDiscard(draft) {
        safeRemove(draft.key);
        deleteDraftFiles(draft.key).then(render);
    }

    function confirmDiscard(draft) {
        var name = draft.leagueName || ('League #' + draft.leagueId);

        if (typeof Swal === 'undefined') {
            if (window.confirm('Discard the unfinished registration for ' + name + '?')) finishDiscard(draft);
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Discard this draft?',
            text: 'The unfinished registration for ' + name + ' and its attached files will be removed from this device.',
            showCancelButton: true,
            confirmButtonText: 'Discard draft',
            cancelButtonText: 'Keep it',
            confirmButtonColor: '#b02a37',
            reverseButtons: true
        }).then(function (result) {
            if (result.isConfirmed) finishDiscard(draft);
        });
    }

    render();

    // Another tab may have saved or discarded a draft.
    window.addEventListener('storage', function (event) {
        if (!event.key || event.key.indexOf(prefix()) === 0) render();
    });

    // Returning via the Back button restores a bfcached page.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) render();
    });
})();
