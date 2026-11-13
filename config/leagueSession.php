<?php
/**
 * Session bootstrap shared by the public league-detail pages
 * (team_information.php, participant.php, schedule.php, standing.php).
 *
 * The portal signs users in on the hardened "LMS_SESSION" session
 * (includes/login_action.php -> config/auth_guard.php -> config/sessionHandler.php),
 * while php.ini defaults to PHPSESSID. Those public pages used to call a bare
 * session_start(), which opened a *different* session, so a logged-in Team
 * Representative was invisible there: the public navbar rendered the guest menu
 * and the league hero offered "Sign in to register".
 *
 * Starting the same session name the portal uses (mirroring the pattern in
 * includes/public_logout.php) lets the role-aware chrome resolve the current
 * user. Anonymous visitors simply get an empty LMS_SESSION, so the public
 * experience is unchanged.
 *
 * Usage: replace the page's session_start() with
 *     require_once __DIR__ . '/config/leagueSession.php';
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('LMS_SESSION');
    session_start();
}
