<?php
/**
 * League Eligibility Validator
 * Validates team and player eligibility against league requirements
 * 
 * Age mapping by division (inclusive upper bounds):
 * - Open: 0-60 years (max 60)
 * - Under - 13: 0-13 years (13 and under)
 * - Under - 18: 0-18 years (18 and under)
 * - Under - 21: 0-21 years (21 and under)
 */

if (file_exists(__DIR__ . '/../config/sportRules.php')) {
    require_once __DIR__ . '/../config/sportRules.php';
}

require_once __DIR__ . '/rosterSize.php';
// The registration-window rules are shared with the league hero and the
// registration page (see validateRegistrationWindow() below).
require_once __DIR__ . '/registrationGate.php';

if (!function_exists('getLeagueRequirements')) {
    function getLeagueRequirements($conn, $leagueId)
    {
        $sql = "SELECT * FROM tbl_league WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $leagueId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}

if (!function_exists('validateRegistrationWindow')) {
    /**
     * Registration-window gate for the submit path.
     *
     * Delegates to resolveRegistrationWindow() / getLeagueStatus() so the POST
     * endpoint can never disagree with the league hero button or the
     * registration page it was reached from.
     *
     * A date-only regis_end is inclusive of the whole closing day there. The
     * previous inline DateTime() math parsed it as 00:00 and rejected every
     * application submitted on the last day, while the page still showed the
     * form as open (and "Registration closed on … 12:00 AM" was the result).
     *
     * @param array    $league League row (regis_start, regis_end,
     *                         registration_window_open, …).
     * @param int|null $nowTs  Reference timestamp; tests pass a fixed clock.
     *
     * @return array{valid:bool,state:string,message:string}
     */
    function validateRegistrationWindow($league, ?int $nowTs = null)
    {
        if (!function_exists('resolveRegistrationWindow')) {
            require_once __DIR__ . '/registrationGate.php';
        }

        $league       = (array) $league;
        $nowTs        = $nowTs ?? time();
        // A live status is required by the shared rule (a Completed or Ongoing
        // league is never open), and it is also what the hero button uses. The
        // reference date comes from the same clock as the window math so tests
        // can pin both.
        $referenceDate = date('Y-m-d', $nowTs);
        $leagueStatus  = function_exists('getLeagueStatus')
            ? getLeagueStatus($league, null, null, null, $referenceDate)
            : 'Open';
        $window        = resolveRegistrationWindow($league, $leagueStatus, false, $nowTs);
        $windowOpen   = (int) ($league['registration_window_open'] ?? 0) === 1;
        $opensLabel   = $window['regStartTs'] ? date('M d, Y', $window['regStartTs']) : '';
        $closesLabel  = $window['regEndTs'] ? date('M d, Y', $window['regEndTs']) : '';

        if ($window['regOpen']) {
            return ['valid' => true, 'state' => 'open', 'message' => ''];
        }

        if ($window['registrationUpcoming']) {
            return [
                'valid'   => false,
                'state'   => 'upcoming',
                'message' => 'Registration has not started yet. Registration opens on ' . $opensLabel . '.',
            ];
        }

        if (!$windowOpen) {
            return [
                'valid'   => false,
                'state'   => 'closed',
                'message' => 'Registration for this league has been closed by the organizer.',
            ];
        }

        if ($window['regEndTs'] && $nowTs > $window['regEndTs']) {
            return [
                'valid'   => false,
                'state'   => 'closed',
                'message' => 'Registration has ended. Registration closed on ' . $closesLabel . '.',
            ];
        }

        if ($leagueStatus !== 'Open') {
            return [
                'valid'   => false,
                'state'   => 'closed',
                'message' => 'Registration is only available while the league is open (current status: ' . $leagueStatus . ').',
            ];
        }

        return [
            'valid'   => false,
            'state'   => 'closed',
            'message' => 'Registration is not available for this league right now.',
        ];
    }
}

if (!function_exists('validateTeamSlots')) {
    function validateTeamSlots($conn, $leagueId, $league)
    {
        // A slot is taken by an APPROVED team only — applications still Pending
        // review must not block other teams from registering.
        $sql = "SELECT COUNT(*) as total FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $leagueId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $registeredTeams = (int) ($row['total'] ?? 0);
        $maxTeams = intval($league['max_team']);
        
        if ($maxTeams > 0 && $registeredTeams >= $maxTeams) {
            return [
                'valid' => false,
                'message' => 'No team slots available. The organizer has already approved the maximum of ' . $maxTeams . ' teams for this league.'
            ];
        }
        
        return [
            'valid' => true,
            'remaining' => max(0, $maxTeams - $registeredTeams)
        ];
    }
}

if (!function_exists('validatePlayerCount')) {
    function validatePlayerCount($players, $league)
    {
        $playerCount = count(array_filter($players, function($p) {
            return !empty($p);
        }));
        
        $minPlayers = max(1, intval($league['min_player'] ?? 5));
        $maxPlayers = intval($league['max_player']);
        // min === max means the roster size is fixed (e.g. Pickleball: 2 players).
        $rosterIsFixed = isFixedRosterSize($minPlayers, $maxPlayers);
        
        if ($playerCount < $minPlayers) {
            return [
                'valid' => false,
                'message' => ($rosterIsFixed ? 'Exactly ' : 'Minimum ') . $minPlayers . ' players required. You have ' . $playerCount . ' player(s).'
            ];
        }
        
        if ($maxPlayers > 0 && $playerCount > $maxPlayers) {
            return [
                'valid' => false,
                'message' => ($rosterIsFixed ? 'Exactly ' : 'Maximum ') . $maxPlayers . ' players required. You have ' . $playerCount . ' player(s).'
            ];
        }
        
        return ['valid' => true];
    }
}

if (!function_exists('validatePlayerAges')) {
    function validatePlayerAges($playerAges, $league)
    {
        $minAge = intval($league['min_age'] ?? 0);
        $maxAge = intval($league['max_age'] ?? 100);
        $invalidPlayers = [];
        
        foreach ($playerAges as $key => $age) {
            if (!empty($age)) {
                $age = intval($age);
                if ($age < $minAge || $age > $maxAge) {
                    $invalidPlayers[] = "Player " . ($key + 1) . " (age: $age)";
                }
            }
        }
        
        if (!empty($invalidPlayers)) {
            return [
                'valid' => false,
                'message' => 'Age requirement: ' . $minAge . '-' . $maxAge . ' years. Invalid: ' . implode(', ', $invalidPlayers)
            ];
        }
        
        return ['valid' => true];
    }
}

if (!function_exists('validateGenderComposition')) {
    function validateGenderComposition($genders, $league)
    {
        $minMale = intval($league['min_male_players'] ?? 0);
        $minFemale = intval($league['min_female_players'] ?? 0);
        
        $maleCount = 0;
        $femaleCount = 0;
        
        foreach ($genders as $gender) {
            if (!empty($gender)) {
                if ($gender === 'Male') {
                    $maleCount++;
                } elseif ($gender === 'Female') {
                    $femaleCount++;
                }
            }
        }
        
        $errors = [];
        
        if ($maleCount < $minMale) {
            $errors[] = 'Minimum ' . $minMale . ' male players required. You have ' . $maleCount . '.';
        }
        
        if ($femaleCount < $minFemale) {
            $errors[] = 'Minimum ' . $minFemale . ' female players required. You have ' . $femaleCount . '.';
        }
        
        if (!empty($errors)) {
            return [
                'valid' => false,
                'message' => implode(' ', $errors)
            ];
        }
        
        return ['valid' => true];
    }
}

if (!function_exists('validateLeagueCategory')) {
    function validateLeagueCategory($genders, $league)
    {
        $category = $league['category'] ?? 'Mix';
        
        if ($category === 'Male') {
            $femaleCount = count(array_filter($genders, function($g) { return $g === 'Female'; }));
            if ($femaleCount > 0) {
                return [
                    'valid' => false,
                    'message' => 'This is a Male-only league. Female players are not allowed.'
                ];
            }
        } elseif ($category === 'Female') {
            $maleCount = count(array_filter($genders, function($g) { return $g === 'Male'; }));
            if ($maleCount > 0) {
                return [
                    'valid' => false,
                    'message' => 'This is a Female-only league. Male players are not allowed.'
                ];
            }
        }
        
        return ['valid' => true];
    }
}


if (!function_exists('findDuplicatePlayers')) {
    /**
     * Players already registered with another active team of the same sport
     * (ANP2026: double entry across teams or divisions is prohibited).
     *
     * @return string[] human-readable findings
     */
    function findDuplicatePlayers($conn, $leagueId, $playerNames, $playerBirthdates, $sport)
    {
        $findings = [];
        if (empty($playerNames)) return $findings;

        $stmt = $conn->prepare("SELECT p.name, p.birthdate, t.team_name, l.league_name
                                FROM tbl_player p
                                JOIN tbl_team t ON t.id = p.team_id
                                JOIN tbl_league l ON l.id = t.league_id
                                WHERE t.league_id <> ? AND (? = '' OR l.sport = ?)
                                  AND LOWER(t.status) NOT IN ('rejected','cancelled','canceled','expired')");
        if (!$stmt) return $findings;
        $stmt->bind_param('iss', $leagueId, $sport, $sport);
        $stmt->execute();
        $res = $stmt->get_result();

        $registered = [];
        while ($row = $res->fetch_assoc()) {
            $key = mb_strtolower(trim((string)$row['name'])) . '|' . (string)$row['birthdate'];
            $registered[$key] = ['team' => $row['team_name'], 'league' => $row['league_name']];
        }
        $stmt->close();

        foreach ($playerNames as $index => $name) {
            $key = mb_strtolower(trim((string)$name)) . '|' . (string)($playerBirthdates[$index] ?? '');
            if (isset($registered[$key])) {
                $findings[] = 'Player ' . ($index + 1) . " ({$name}) is already registered with \"{$registered[$key]['team']}\" in {$registered[$key]['league']}.";
            }
        }
        return $findings;
    }
}


if (!function_exists('validateJerseyRange')) {
    /** Jersey numbers must sit inside the sport's range (Volleyball: 1–99). */
    function validateJerseyRange($jerseys, array $profile): array
    {
        $min = (int)($profile['jersey_min'] ?? 0);
        $max = (int)($profile['jersey_max'] ?? 999);
        if ($min <= 0 && $max >= 999) return ['valid' => true];
        foreach ($jerseys as $index => $jersey) {
            if (!is_scalar($jersey) || trim((string)$jersey) === '') continue;
            $value = (int)$jersey;
            if ($value < $min || $value > $max) {
                return ['valid' => false, 'message' => 'Player ' . ($index + 1) . ": jersey number must be between {$min} and {$max}."];
            }
        }
        return ['valid' => true];
    }
}


if (!function_exists('validateAllEligibility')) {
    function validateAllEligibility($conn, $leagueId, $playerNames, $playerAges, $genders, array $extra = [])
    {
        $league = getLeagueRequirements($conn, $leagueId);
        
        if (!$league) {
            return [
                'valid' => false,
                'message' => 'League not found.'
            ];
        }
        
        // Check registration window
        $windowCheck = validateRegistrationWindow($league);
        if (!$windowCheck['valid']) {
            return $windowCheck;
        }
        
        // Check team slots
        $slotsCheck = validateTeamSlots($conn, $leagueId, $league);
        if (!$slotsCheck['valid']) {
            return $slotsCheck;
        }
        
        // Check roster size — every submitted roster row counts as a player.
        $countCheck = validatePlayerCount($playerNames, $league);
        if (!$countCheck['valid']) {
            return $countCheck;
        }

        // Check player ages
        $ageCheck = validatePlayerAges($playerAges, $league);
        if (!$ageCheck['valid']) {
            return $ageCheck;
        }
        
        // Check gender composition
        $genderCheck = validateGenderComposition($genders, $league);
        if (!$genderCheck['valid']) {
            return $genderCheck;
        }
        
        // Check league category
        $categoryCheck = validateLeagueCategory($genders, $league);
        if (!$categoryCheck['valid']) {
            return $categoryCheck;
        }

        // Double entry: the same player in another active team of the same sport
        $birthdates = $extra['birthdates'] ?? [];
        if (!empty($birthdates)) {
            $duplicates = findDuplicatePlayers($conn, $leagueId, $playerNames, $birthdates, (string)($league['sport'] ?? ''));
            if (!empty($duplicates)) {
                return [
                    'valid'   => false,
                    'message' => 'Double entry is prohibited — ' . implode(' ', array_slice($duplicates, 0, 3)),
                ];
            }
        }

        return ['valid' => true];
    }
}

if (!function_exists('getEligibilityRequirements')) {
    function getEligibilityRequirements($conn, $leagueId)
    {
        $league = getLeagueRequirements($conn, $leagueId);
        
        if (!$league) {
            return null;
        }
        
        return [
            'min_age' => intval($league['min_age'] ?? 0),
            'max_age' => intval($league['max_age'] ?? 100),
            'min_male_players' => intval($league['min_male_players'] ?? 0),
            'min_female_players' => intval($league['min_female_players'] ?? 0),
            'min_players' => max(1, intval($league['min_player'] ?? 5)),
            'max_players' => intval($league['max_player'] ?? 0),
            'category' => $league['category'] ?? 'Mix',
            'division' => $league['division'] ?? 'Open',
            'start_date' => $league['start_date'] ?? date('Y-m-d')
        ];
    }
}

?>
