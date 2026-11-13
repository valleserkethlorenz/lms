<?php
/**
 * Registration Gate — single source of truth for "may this team representative
 * register a team for this league?".
 *
 * The rules used to live inline in includes/league_header.php. They are now
 * resolved here so the public league hero (register button state) and the
 * dedicated registration page (app/tr/register_team.php) can never disagree.
 *
 * Usage:
 *   require_once __DIR__ . '/registrationGate.php';
 *   $gate = getRegistrationGate($conn, $leagueId, $userId);
 *
 * Returned keys map 1:1 onto the variables league_header.php already used.
 */

require_once __DIR__ . '/../config/leagueStatus.php';

if (!function_exists('resolveRegistrationWindow')) {
    /**
     * Registration window math for one league row.
     *
     * Pure (no DB, no clock reads when $nowTs is supplied) so the rules the
     * public hero button and app/tr/register_team.php share can be unit-tested.
     *
     * @param array  $league       League row (regis_start, regis_end, registration_window_open).
     * @param string $leagueStatus Result of getLeagueStatus($league).
     * @param bool   $slotsFull    Whether every team slot is taken.
     * @param int|null $nowTs      Reference timestamp (defaults to time()).
     *
     * @return array{regOpen: bool, regStartTs: int|false, regEndTs: int|false, registrationUpcoming: bool, registrationLabel: string}
     */
    function resolveRegistrationWindow(array $league, string $leagueStatus, bool $slotsFull = false, ?int $nowTs = null): array
    {
        $nowTs       = $nowTs ?? time();
        $regStartTs  = strtotime((string) ($league['regis_start'] ?? ''));
        $regEndValue = $league['regis_end'] ?? '';
        // A date-only end date is inclusive of the whole day.
        $regEndTs    = strtotime($regEndValue . (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $regEndValue) ? ' 23:59:59' : ''));
        $windowOpen  = (int) ($league['registration_window_open'] ?? 0) === 1;

        $regOpen = ($regStartTs && $regEndTs && $nowTs >= $regStartTs && $nowTs <= $regEndTs && $windowOpen);
        // The league's live status must also be "Open"
        $regOpen = $regOpen && ($leagueStatus === 'Open');

        $registrationUpcoming = $regStartTs && $nowTs < $regStartTs;

        $registrationLabel = $registrationUpcoming
            ? 'Opens ' . date('M j, Y', $regStartTs)
            : ((!$regEndTs || $nowTs > $regEndTs || !$windowOpen)
                ? 'Registration Closed'
                : ($slotsFull ? 'Registration Full' : 'Registration Closed'));

        return [
            'regOpen'              => (bool) $regOpen,
            'regStartTs'           => $regStartTs,
            'regEndTs'             => $regEndTs,
            'registrationUpcoming' => (bool) $registrationUpcoming,
            'registrationLabel'    => $registrationLabel,
        ];
    }
}

if (!function_exists('resolveRegistrationWindowSummary')) {
    /**
     * Explain whether registration is available and, if not, why.
     *
     * @param array       $league       League row.
     * @param int         $approvedTeams Number of approved teams.
     * @param string|null $today        Reference date (YYYY-MM-DD), defaults to today.
     *
     * @return array{status:string,note:string,isOpen:bool}
     */
    function resolveRegistrationWindowSummary(array $league, int $approvedTeams, ?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $regStart = trim((string) ($league['regis_start'] ?? ''));
        $regEnd = trim((string) ($league['regis_end'] ?? ''));
        $leagueEnd = trim((string) ($league['end_date'] ?? ''));

        if (($league['publication_status'] ?? '') !== 'Published') {
            return ['status' => 'Not published', 'note' => 'Publish the league before accepting registrations.', 'isOpen' => false];
        }
        if ((int) ($league['is_archived'] ?? 0) === 1) {
            return ['status' => 'Archived', 'note' => 'Archived leagues cannot accept registrations.', 'isOpen' => false];
        }
        if ((int) ($league['registration_window_open'] ?? 0) !== 1) {
            return ['status' => 'Manually closed', 'note' => 'Registration was closed by the organizer.', 'isOpen' => false];
        }
        if ($leagueEnd !== '' && $today > $leagueEnd) {
            return ['status' => 'League ended', 'note' => 'This league has already ended.', 'isOpen' => false];
        }
        if ($regStart === '' || $regEnd === '' || !strtotime($regStart) || !strtotime($regEnd)) {
            return ['status' => 'Dates incomplete', 'note' => 'Set valid registration start and end dates.', 'isOpen' => false];
        }
        if ($today < $regStart) {
            return [
                'status' => 'Upcoming',
                'note' => 'Registration opens ' . date('M j, Y', strtotime($regStart)) . '.',
                'isOpen' => false,
            ];
        }
        if ($today > $regEnd) {
            return [
                'status' => 'Ended',
                'note' => 'Registration ended ' . date('M j, Y', strtotime($regEnd)) . '.',
                'isOpen' => false,
            ];
        }
        if ($approvedTeams >= max(1, (int) ($league['max_team'] ?? 1))) {
            return ['status' => 'Full', 'note' => 'All team slots are filled.', 'isOpen' => false];
        }

        return ['status' => 'Open', 'note' => 'Teams can apply now.', 'isOpen' => true];
    }
}

if (!function_exists('resolveRegistrationReopenState')) {
    /**
     * Whether a manually closed registration window may be reopened.
     *
     * Pure (no DB, no clock reads when $today is supplied) so the league
     * overview button and the reopen endpoint share one rule and can be
     * unit-tested. Mirrors the ordering of resolveRegistrationWindowSummary():
     * publication, archive, existing state, league end, dates, slots.
     *
     * @param array       $league        League row.
     * @param int         $approvedTeams Number of approved teams.
     * @param string|null $today         Reference date (YYYY-MM-DD), defaults to today.
     *
     * @return array{allowed:bool, reason:string}
     */
    function resolveRegistrationReopenState(array $league, int $approvedTeams, ?string $today = null): array
    {
        $today     = $today ?? date('Y-m-d');
        $regStart  = trim((string) ($league['regis_start'] ?? ''));
        $regEnd    = trim((string) ($league['regis_end'] ?? ''));
        $leagueEnd = trim((string) ($league['end_date'] ?? ''));
        $maxTeams  = max(1, (int) ($league['max_team'] ?? 1));

        if (($league['publication_status'] ?? '') !== 'Published') {
            return ['allowed' => false, 'reason' => 'Publish the league before reopening registration.'];
        }
        if ((int) ($league['is_archived'] ?? 0) === 1) {
            return ['allowed' => false, 'reason' => 'Archived leagues cannot accept registrations.'];
        }
        if ((int) ($league['registration_window_open'] ?? 0) === 1) {
            return ['allowed' => false, 'reason' => 'Registration is already open.'];
        }
        if ($leagueEnd !== '' && $today > $leagueEnd) {
            return ['allowed' => false, 'reason' => 'This league has already ended.'];
        }
        if ($regStart === '' || $regEnd === '' || !strtotime($regStart) || !strtotime($regEnd)) {
            return ['allowed' => false, 'reason' => 'Set valid registration start and end dates first.'];
        }
        if ($today > $regEnd) {
            return ['allowed' => false, 'reason' => 'The registration end date has passed. Extend it in the league settings first.'];
        }
        if ($approvedTeams >= $maxTeams) {
            return ['allowed' => false, 'reason' => 'All team slots are filled.'];
        }

        return ['allowed' => true, 'reason' => ''];
    }
}

if (!function_exists('resolveRegistrationState')) {
    /**
     * Which state the caller should render. Order matters: an upcoming window
     * always wins, then a full league, then a closed one, then an existing
     * application, and only then the open form.
     */
    function resolveRegistrationState(bool $regOpen, bool $registrationUpcoming, bool $slotsFull, bool $hasApplication): string
    {
        if ($registrationUpcoming) {
            return 'upcoming';
        }
        if ($regOpen && $slotsFull) {
            return 'full';
        }
        if (!$regOpen) {
            return 'closed';
        }
        if ($hasApplication) {
            return 'already_registered';
        }
        return 'open';
    }
}

if (!function_exists('getRegistrationGate')) {
    function getRegistrationGate(mysqli $conn, int $leagueId, int $userId = 0): array
    {
        $gate = [
            'state'                     => 'not_found',
            'league'                    => null,
            'leagueStatus'              => '',
            'leagueStatusLabel'         => '',
            'leagueStatusPill'          => 'lp-pill-default',
            'regOpen'                   => false,
            'regStartTs'                => false,
            'regEndTs'                  => false,
            'registrationUpcoming'      => false,
            'registrationLabel'         => 'Registration Closed',
            'registeredCount'           => 0,
            'maxTeam'                   => 0,
            'slotsFull'                 => false,
            'hasApplication'            => false,
            'existingApplicationStatus' => '',
        ];

        if ($leagueId <= 0) {
            return $gate;
        }

        $stmt = $conn->prepare(
            "SELECT l.*, CONCAT(IFNULL(u.fname, ''), ' ', IFNULL(u.lname, '')) AS organizer_name
             FROM tbl_league l
             LEFT JOIN tbl_user u ON l.created_by = u.id
             WHERE l.id=? AND l.publication_status='Published' AND (l.is_archived=0 OR l.is_archived IS NULL)
             LIMIT 1"
        );
        if (!$stmt) {
            return $gate;
        }
        $stmt->bind_param('i', $leagueId);
        $stmt->execute();
        $league = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$league) {
            return $gate;
        }

        /* -----------------------------------------------------------------
           Registration window + slot state (rules live in
           resolveRegistrationWindow(), which is unit-tested).
           ----------------------------------------------------------------- */
        $leagueStatus      = getLeagueStatus($league);
        $leagueStatusLabel = getLeagueStatusLabel($leagueStatus);

        // Slots are filled by APPROVED teams only — a Pending application is
        // still waiting for the organizer's decision and must neither close the
        // window nor put a "Slots full" pill on the hero.
        $slotsFull       = false;
        $maxTeam         = intval($league['max_team'] ?? 0);
        $registeredCount = countLeagueApprovedTeams($conn, $leagueId);
        $slotsFull       = leagueSlotState($registeredCount, $maxTeam)['slotsFull'];

        $window               = resolveRegistrationWindow($league, $leagueStatus, $slotsFull);
        $regOpen              = $window['regOpen'];
        $regStartTs           = $window['regStartTs'];
        $regEndTs             = $window['regEndTs'];
        $registrationUpcoming = $window['registrationUpcoming'];
        $registrationLabel    = $window['registrationLabel'];

        // Does the signed-in representative already have an entry for this league?
        $hasApplication            = false;
        $existingApplicationStatus = '';
        if ($regOpen && $userId > 0) {
            $appCheck = $conn->prepare("SELECT status FROM tbl_team WHERE user_id = ? AND league_id = ? ORDER BY id DESC LIMIT 1");
            if ($appCheck) {
                $appCheck->bind_param('ii', $userId, $leagueId);
                $appCheck->execute();
                $applicationResult = $appCheck->get_result();
                if ($applicationRow = $applicationResult->fetch_assoc()) {
                    $hasApplication = true;
                    $existingApplicationStatus = strtolower((string) $applicationRow['status']);
                }
                $appCheck->close();
            }
        }

        /* -----------------------------------------------------------------
           Resolve the state the caller should render.
           ----------------------------------------------------------------- */
        $state = resolveRegistrationState($regOpen, $registrationUpcoming, $slotsFull, $hasApplication);

        return array_merge($gate, [
            'state'                     => $state,
            'league'                    => $league,
            'leagueStatus'              => $leagueStatus,
            'leagueStatusLabel'         => $leagueStatusLabel,
            'leagueStatusPill'          => 'lp-pill-' . strtolower($leagueStatus),
            'regOpen'                   => $regOpen,
            'regStartTs'                => $regStartTs,
            'regEndTs'                  => $regEndTs,
            'registrationUpcoming'      => $registrationUpcoming,
            'registrationLabel'         => $registrationLabel,
            'registeredCount'           => (int) $registeredCount,
            'maxTeam'                   => $maxTeam,
            'slotsFull'                 => $slotsFull,
            'hasApplication'            => $hasApplication,
            'existingApplicationStatus' => $existingApplicationStatus,
        ]);
    }
}
