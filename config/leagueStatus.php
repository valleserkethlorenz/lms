<?php
/**
 * League Status Helper Functions
 * Provides real-time status calculation for leagues based on dates
 */

/**
 * Calculate real-time league status based on league dates
 * 
 * @param string $start_date Start date of the league (YYYY-MM-DD format)
 * @param string $end_date End date of the league (YYYY-MM-DD format)
 * @param string $regis_start Registration start date (YYYY-MM-DD format)
 * @param string $regis_end Registration end date (YYYY-MM-DD format)
 * @return string League status: 'Open', 'Ongoing', 'Upcoming', or 'Ended'
 */
/**
 * Determine league status based on provided data.
 * Accepts either:
 *  - (start_date_string, end_date_string, regis_start, regis_end)
 *  - or a full $league_row array as the first argument
 *
 * Logic implemented per requested definitions:
 *  - "Upcoming": today < regis_start
 *  - "Open": regis_start <= today <= regis_end AND registration_window_open != 0 AND slots are available
 *  - "Ongoing": registration window ended OR slots full OR registration manually closed, and today <= end_date
 *  - "Completed": today > end_date
 *
 * A slot is only taken by an APPROVED team (countLeagueApprovedTeams()).
 * Applications that are still Pending are requests awaiting the organizer's
 * review, so they never fill a league.
 */

if (!function_exists('countLeagueApprovedTeams')) {
    /**
     * How many teams currently occupy a slot in one league.
     *
     * Slots belong to teams the organizer has APPROVED. Pending applications
     * (and those returned for correction) are still waiting for a decision, so
     * they must never consume a slot — otherwise a league reads "Slots full"
     * and flips to Ongoing before the organizer has approved anyone, which
     * closes registration for every other team.
     *
     * @param mysqli|null $conn
     * @param int         $leagueId
     * @return int
     */
    function countLeagueApprovedTeams($conn, $leagueId): int
    {
        $leagueId = (int) $leagueId;
        if ($leagueId <= 0 || !($conn instanceof mysqli)) {
            return 0;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved'");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $leagueId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['cnt'] ?? 0);
    }
}

if (!function_exists('leagueSlotState')) {
    /**
     * Slot math for one league. Pure (no DB, no clock) so the hero banner, the
     * KPI row, the public directory and the registration gate can never
     * disagree about how many slots are left.
     *
     * @param int $approvedTeams Teams occupying a slot (approved only).
     * @param int $maxTeams      tbl_league.max_team.
     *
     * @return array{approvedTeams:int, maxTeams:int, slotsLeft:int, slotsFull:bool, fillPercentage:int}
     */
    function leagueSlotState($approvedTeams, $maxTeams): array
    {
        $approvedTeams = max(0, (int) $approvedTeams);
        $maxTeams      = max(0, (int) $maxTeams);

        return [
            'approvedTeams'  => $approvedTeams,
            'maxTeams'       => $maxTeams,
            'slotsLeft'      => max(0, $maxTeams - $approvedTeams),
            'slotsFull'      => $maxTeams > 0 && $approvedTeams >= $maxTeams,
            'fillPercentage' => $maxTeams > 0 ? min(100, (int) round($approvedTeams / $maxTeams * 100)) : 0,
        ];
    }
}

function getLeagueStatus($a, $b = null, $regis_start = null, $regis_end = null) {
    $today = date('Y-m-d');

    // Normalize inputs - allow passing $league_row as first arg
    $league = null;
    if (is_array($a)) {
        $league = $a;
        $start_date = $league['start_date'] ?? null;
        $end_date = $league['end_date'] ?? null;
        $regis_start = $league['regis_start'] ?? $regis_start;
        $regis_end = $league['regis_end'] ?? $regis_end;
        $max_team = isset($league['max_team']) ? intval($league['max_team']) : null;
        $league_id = isset($league['id']) ? intval($league['id']) : null;
        $registration_window_open = isset($league['registration_window_open']) ? intval($league['registration_window_open']) : null;
    } else {
        $start_date = $a;
        $end_date = $b;
        $max_team = null;
        $league_id = null;
        $registration_window_open = null;
    }

    /* ---------------------------------------------------------------------
       Slot state. Only APPROVED teams take a slot (countLeagueApprovedTeams),
       so pending applications can never close registration on their own.
       Callers that already counted the approved teams may pass the number in
       as $league['current_teams'] to skip the extra query.
       --------------------------------------------------------------------- */
    $slots_full = false;
    $current_teams = 0;
    if (isset($league_id) && $league_id) {
        if ($max_team === null && isset($league['max_team'])) $max_team = intval($league['max_team']);

        // try to use global $conn if available
        global $conn;
        $hasConnection = function_exists('mysqli_connect') && isset($conn) && $conn instanceof mysqli;

        if (is_array($league) && array_key_exists('current_teams', $league)) {
            $current_teams = intval($league['current_teams']);
        } elseif ($hasConnection) {
            $current_teams = countLeagueApprovedTeams($conn, $league_id);
        }

        if ($hasConnection) {
            // if registration_window_open not provided in $league, try to fetch
            if ($registration_window_open === null) {
                $rstmt = $conn->prepare("SELECT registration_window_open FROM tbl_league WHERE id = ? LIMIT 1");
                if ($rstmt) {
                    $rstmt->bind_param('i', $league_id);
                    $rstmt->execute();
                    $rres = $rstmt->get_result();
                    $rrow = $rres->fetch_assoc();
                    $registration_window_open = isset($rrow['registration_window_open']) ? intval($rrow['registration_window_open']) : null;
                    $rstmt->close();
                }
            }
        }

        $slots_full = leagueSlotState($current_teams, $max_team)['slotsFull'];
    }

    // Completed has highest precedence
    if ($end_date && $today > $end_date) {
        return 'Completed';
    }

    // If registration start is known
    if ($regis_start) {
        if ($today < $regis_start) {
            return 'Upcoming';
        }

        // Registration period active
        if ($regis_end && $today >= $regis_start && $today <= $regis_end) {
            // registration_window_open must not be explicitly 0
            if ($registration_window_open === 0) {
                return 'Ongoing';
            }
            // if slots are full, treat as Ongoing
            if ($slots_full) {
                return 'Ongoing';
            }
            return 'Open';
        }

        // After registration end
        if ($regis_end && $today > $regis_end) {
            return 'Ongoing';
        }

        // If we reach here, fallback to checking start_date
    }

    // Fallbacks based on start/end
    if ($start_date && $end_date) {
        if ($today < $start_date) {
            // If regis_start missing but start_date in future, assume Upcoming
            return 'Upcoming';
        }
        if ($today >= $start_date && $today <= $end_date) {
            return 'Ongoing';
        }
        if ($today > $end_date) {
            return 'Completed';
        }
    }

    // Default
    return 'Upcoming';
}

/**
 * Get the display label for league status
 * Used for badge displays where 'Ongoing' should show as 'OPEN'
 * 
 * @param string $status League status from getLeagueStatus()
 * @return string Display label for the badge
 */
function getLeagueStatusLabel($status) {
    $map = [
        'Open' => 'OPEN',
        'Ongoing' => 'ONGOING',
        'Upcoming' => 'UPCOMING',
        'Completed' => 'COMPLETED'
    ];
    return $map[$status] ?? strtoupper($status);
}

/**
 * Get the CSS class for league status badge
 * 
 * @param string $status League status from getLeagueStatus()
 * @return string CSS class name for styling
 */
function getLeagueStatusClass($status) {
    $statusMap = [
        'Open' => 'league-status-open',
        'Ongoing' => 'league-status-ongoing',
        'Upcoming' => 'league-status-upcoming',
        'Completed' => 'league-status-ended'
    ];
    
    return $statusMap[$status] ?? 'league-status-' . strtolower($status);
}

/**
 * Get badge HTML for league status
 * 
 * @param string $status League status from getLeagueStatus()
 * @return string HTML badge element
 */
function getLeagueStatusBadge($status) {
    $label = getLeagueStatusLabel($status);
    $class = getLeagueStatusClass($status);
    return '<span class="league-badge-status ' . $class . '">' . $label . '</span>';
}

/**
 * Get Bootstrap badge class for organizer views
 * 
 * @param string $status League status from getLeagueStatus()
 * @return string Bootstrap badge class
 */
function getLeagueStatusBadgeClass($status) {
    $statusMap = [
        'Open' => 'text-bg-success',
        'Ongoing' => 'text-bg-primary',
        'Upcoming' => 'text-bg-warning',
        'Completed' => 'text-bg-secondary'
    ];
    
    return $statusMap[$status] ?? 'text-bg-primary';
}

/**
 * Get display text for organizer views
 * 
 * @param string $status League status from getLeagueStatus()
 * @return string Display text
 */
function getLeagueStatusDisplay($status) {
    $displayMap = [
        'Ongoing' => 'Ongoing',
        'Open' => 'Open',
        'Upcoming' => 'Upcoming',
        'Completed' => 'Completed'
    ];
    
    return $displayMap[$status] ?? $status;
}
?>
