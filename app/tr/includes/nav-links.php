<?php
/**
 * Team Representative portal menu — single source of truth for the sidebar.
 *
 * Hrefs are relative to the portal directory (lms/app/tr/). Renderers that live
 * elsewhere (e.g. includes/bar-tr-league.php, used by the public league-detail
 * pages) prefix them with their own base path.
 *
 * "key" drives the active state so a page can highlight an entry even when its
 * URL does not contain the href (the league Overview / Participants / Schedule
 * / Standings tabs all keep "Browse League" selected).
 */
return [
    ['key' => 'dashboard', 'id' => 'dashboard-item', 'label' => 'Dashboard',     'icon' => 'bi-speedometer2', 'href' => 'dashboard.php'],
    ['key' => 'browse',    'id' => 'browse-item',    'label' => 'Browse League', 'icon' => 'bi-search',       'href' => 'browse_leagues.php'],
    ['key' => 'league',    'id' => 'leagues-item',   'label' => 'Application',   'icon' => 'bi-trophy',       'href' => 'league.php'],
    ['key' => 'account',   'id' => 'account-item',   'label' => 'My Account',    'icon' => 'bi-person-gear',  'href' => 'account.php'],
];
