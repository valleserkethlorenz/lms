- [x] Identify where the register button/modal trigger is rendered for league cards (league_header.php)
- [x] Verify league status value sources: getLeagueStatus(), tbl_league.stat, registration_window_open
- [x] Pinpoint why regisTeam modal is always available/open (likely unconditional include + JS trigger without status gating)
- [x] Implement fix: add server-side guard in registerTeam.php against registration window
- [x] Implement fix: hide/disable "Register Team" trigger when status is Ongoing/Completed/closed in league_header.php
- [x] Add league live status check in registerTeam.php server-side guard (defense in depth)
- [x] Fix SQL injection vulnerabilities in login_action.php (all queries now use prepared statements)
- [x] Add password complexity validation in login_action.php (8+ chars, uppercase, lowercase, number, special char)
- [x] Fix password escaping bug in login_action.php (password no longer escaped before password_verify)

- [x] Add debugging log entry for computed league status at click/submit
- [ ] Test with an Ongoing league: button must not open; direct POST should be rejected
- [x] Clean up duplicate/stale project files in root directory
- [x] Standardize authentication patterns (replace manual role checks with requireRole() guard)
- [x] CSRF token audit: all POST forms and action endpoints now have CSRF protection
- [ ] XSS output escaping audit across view templates

## Team registration: modal → dedicated page (2026-10-26)

Problem: the `#regisTeam` modal held a 5–10 minute, document-heavy form with no
persistence. Closing it by accident (✕, Close, refresh, Back, or a league tab)
lost everything.

- [x] Extract the registration rules into `includes/registrationGate.php` so the
      hero button state and the registration page can never disagree
- [x] Add `app/tr/register_team.php` (role 2 only) with explicit states:
      open / upcoming / closed / slots full / already registered / not found
- [x] `includes/league_header.php`: "Register team" now points at the page via
      `data-register-href`; countdown disable/relabel behaviour preserved
- [x] Draft autosave + resume (localStorage, key = representative + league,
      7-day expiry, versioned, cleared on successful submit or discard)
- [x] "Re-attach these files" checklist on resume — the safety net for browsers
      that cannot repopulate file inputs
- [x] File blobs persisted in IndexedDB (`lms_team_reg_drafts`) and re-attached
      through `DataTransfer` on resume; per-draft 25 MB cap, 7-day purge, all
      failures degrade to the checklist above
- [x] `beforeunload` guard + "Save draft & exit" + "Discard draft"
- [x] Success now redirects to the applications page instead of reloading
- [x] "Unfinished registrations" panel on the TR dashboard and applications page:
      `includes/registration-drafts-panel.php` + `js/registration-drafts.js` list
      this device's drafts (league name, player count, saved time), link straight
      back to `register_team.php?id=…`, and discard both the draft and its files
- [x] Unit coverage for the shared rules — `tests/Unit/TeamRegistrationStateTest.php`
      (window math + state precedence, 20 tests) via the extracted
      `resolveRegistrationWindow()` / `resolveRegistrationState()`
- [x] `includes/league_header.php` + `includes/league-view-styles.php`: the public
      league breadcrumb (Overview / Participants / Schedule / Standings) now uses
      the organizer trail — hub › league name (link to Overview) › current page —
      with chevron separators and borderless chips
- [ ] Browser sign-off: four league pages (team_information, participant,
      schedule, standing) across all five button states, desktop + mobile
- [x] `app/tr/register_team.php`: the TR page hero now renders the same
      `lp-hero` banner the public league pages use (`includes/league-view-styles.php`),
      with an organizer-style breadcrumb trail above it
      (`Browse League › league name › Team registration`, chevron separators);
      the "Back to leagues" hero button and the duplicate summary chips are gone
      (slot availability moved into the hero meta)
- [x] Commit 2: deleted `components/modal/registeam.php`, its two `include`
      lines (`includes/league_header.php`, `leagues.php`) and the dead
      `.openRegisterModal` handler in `leagues.php`

## League creation: modal → dedicated page (2026-11-03)

Problem: the `#addCat` dialog (`components/modal/orgModal.php`) held the whole
league draft form — cramped at two columns, impossible to link to, and gone on
an accidental backdrop click or refresh.

- [x] Add `app/organizer/create_league.php` (role 1 only) with the organizer
      breadcrumb (`League Management › Create League`), the shared hero
      (`includes/page-hero.php`) and the draft form in one panel grouped as
      identity / schedule & venue / roster / description / documents
- [x] `league.php`: the hero "Create League" button is a plain link to the page
      instead of a `data-bs-toggle="modal"` trigger
- [x] Save path unchanged — the page still posts to `action/leagueAction.php`
      with the `regisLeague` marker, so the league is created as a Draft and the
      "Setup now / Set up later" step still runs (`window.lmsAfterLeagueCreated`)
- [x] Retired the modal: removed its four `include` lines (`league.php`,
      `league_info.php`, `pending_teams.php`, `view_player.php`), the dead
      `#regisLeague` submit handler in `league_info.php`, and deleted
      `components/modal/orgModal.php`
- [x] `includes/bar.php`: `create_league.php` added to the Leagues submenu URL
      list so the sidebar keeps that section open on the new page
- [x] Fixed the division age note (`#eligibilityAlert` / `#ageRange`) the old
      modal script referenced but never rendered
- [ ] Browser sign-off: create one league end-to-end (image + guideline document
      + document requirement), desktop and mobile widths

## Registration window + document storage (2026-11-16)

Problem: on the closing day of a league’s registration window the page, the
hero button and `getLeagueStatus()` all read the window as open, but the POST
endpoint rejected every submission with “Registration has ended. Registration
closed on <date> 12:00 AM”. `includes/eligibilityValidator.php` parsed the
date-only `regis_end` (the organizer form is `<input type="date">`) with
`new DateTime()`, i.e. 00:00, while `includes/registrationGate.php` treats the
end date as inclusive of the whole day.

- [x] `validateRegistrationWindow()` now delegates to
      `resolveRegistrationWindow()` + `getLeagueStatus()` (one rule set for the
      hero, the page and the POST) and returns `{valid, state, message}`
- [x] `getLeagueStatus()` accepts a reference date so the POST gate and the
      tests share one clock
- [x] `registerTeam.php` answers `403 {state}` from that gate and releases the
      session lock before the heavy work
- [x] `413` for a body larger than `post_max_size` instead of a bogus 419
- [x] Client: `novalidate` + a complete required-field summary (documents
      included), photo downscaling before upload, upload timeout and readable
      network errors, `419` handled like `401`
- [x] Mobile roster rendered as labelled cards (≤767 px), 16 px controls and a
      sticky action bar (≤575 px)
- [x] Heroku limits: `fpm_custom.conf` + `-F` in the Procfile, mirrored in
      `.user.ini` and `.htaccess`
- [x] Submitted documents: rollbacks and replacements now delete the Cloudinary
      asset as well (`includes/documentAssets.php`), and a cloud outage falls
      back to the validated local copy instead of rejecting the submission
- [x] League overview documents download through
      `includes/downloadLeagueDocument.php` (Cloudinary links used to open in a
      new tab; `download` is ignored cross-origin)
- [x] Hosted 30 s proxy limit: registration commits the accepted files locally,
      answers `201`, and only then publishes them to Cloudinary (after
      `fastcgi_finish_request()`), so a full roster no longer dies with HTTP 503.
      The automatic checker is capped by `LMS_DOCUMENT_SYNC_BUDGET` (12 s on
      Heroku) and unfinished documents fall back to manual review; the client
      downscales photos harder (1600 px / 0.8) because the proxy also counts the
      upload time. Cloudinary calls now carry real upload/connect timeouts.
- [x] Tests: `RegistrationWindowGateTest`, `DocumentAssetsTest`
- [ ] Browser sign-off on a real phone: closing-day submission, missing-document
      summary, six camera photos, correction resubmission
- [ ] Move submitted documents to authenticated (signed) Cloudinary delivery —
      the stored URLs are currently public-but-unlisted
- [x] Document preview modal (organizer team review + representative team view):
      zoom + pan for images **and** PDFs — toolbar buttons, mouse wheel,
      two-finger pinch, double-tap, drag to pan, and `+` / `−` / `0` shortcuts.
      A PDF keeps its own viewer at 100% and gets a pointer shield once scaled;
      "Open in new tab" was added for phones that prefer the native PDF viewer.
      Shared component: `includes/documentPreviewModal.php`

