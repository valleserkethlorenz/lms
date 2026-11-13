<?php
/**
 * Set / series match results (Volleyball ANP2026 and any future set-based sport)
 * ----------------------------------------------------------------------------
 * Convention: tbl_schedule.a_score / b_score keep meaning "the unit that decides
 * the match" — sets won for set-based sports, points for every other sport — so
 * all existing advancement, forfeit and protest logic keeps working unchanged.
 *
 * The per-set detail lives in tbl_match_set, and a_points/b_points hold the total
 * points won across sets (used by the point-ratio tiebreak).
 */

if (!function_exists('getMatchSets')) {
    /** Ordered sets for a match. @return array<int,array{set_no:int,a_points:int,b_points:int,is_deciding:int}> */
    function getMatchSets($conn, int $scheduleId): array
    {
        $sets = [];
        $stmt = @$conn->prepare("SELECT set_no, a_points, b_points, is_deciding FROM tbl_match_set WHERE schedule_id = ? ORDER BY set_no ASC");
        if (!$stmt) return $sets;
        $stmt->bind_param('i', $scheduleId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $sets[] = [
                'set_no'      => (int)$row['set_no'],
                'a_points'    => (int)$row['a_points'],
                'b_points'    => (int)$row['b_points'],
                'is_deciding' => (int)$row['is_deciding'],
            ];
        }
        $stmt->close();
        return $sets;
    }
}

if (!function_exists('deleteMatchSets')) {
    function deleteMatchSets($conn, int $scheduleId): void
    {
        $stmt = @$conn->prepare("DELETE FROM tbl_match_set WHERE schedule_id = ?");
        if (!$stmt) return;
        $stmt->bind_param('i', $scheduleId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('parseSetInput')) {
    /**
     * Normalise the set_a[] / set_b[] form fields into [[a,b], ...], skipping
     * blank rows. Returns null when a row is only half filled.
     * @return array<int,array{0:int,1:int}>|null
     */
    function parseSetInput(array $aValues, array $bValues): ?array
    {
        $sets = [];
        $count = max(count($aValues), count($bValues));
        for ($i = 0; $i < $count; $i++) {
            $aRaw = trim((string)($aValues[$i] ?? ''));
            $bRaw = trim((string)($bValues[$i] ?? ''));
            if ($aRaw === '' && $bRaw === '') continue;
            if ($aRaw === '' || $bRaw === '' || !is_numeric($aRaw) || !is_numeric($bRaw)) return null;
            $sets[] = [(int)$aRaw, (int)$bRaw];
        }
        return $sets;
    }
}

if (!function_exists('validateSetSeries')) {
    /**
     * Validate a whole best-of series (each set + completion + no extra sets).
     * @param array<int,array{0:int,1:int}> $sets
     */
    function validateSetSeries(array $profile, int $bestOf, array $sets): ?string
    {
        $bestOf = max(1, $bestOf);
        if (empty($sets)) return 'Enter the set scores.';
        if (count($sets) > $bestOf) {
            return 'A best-of-' . $bestOf . ' match cannot have ' . count($sets) . ' sets.';
        }

        $needed = sportProfileWinningSets($profile, $bestOf);
        $setsA = 0; $setsB = 0;
        foreach ($sets as $index => $pair) {
            $setNo = $index + 1;
            $a = (int)$pair[0];
            $b = (int)$pair[1];
            $error = sportProfileValidateSet($profile, $setNo, $bestOf, $a, $b);
            if ($error) return $error;
            if ($a > $b) $setsA++; else $setsB++;
            if ($setNo < count($sets) && ($setsA >= $needed || $setsB >= $needed)) {
                return "Set {$setNo} already decided the match — remove the extra set(s).";
            }
        }
        if ($setsA < $needed && $setsB < $needed) {
            return "The match is incomplete: a best-of-{$bestOf} needs {$needed} set win(s) — currently {$setsA}-{$setsB}.";
        }
        return null;
    }
}

if (!function_exists('matchBestOfForSchedule')) {
    /**
     * Best-of for a specific match: match column -> stage column -> profile.
     * $isFinal uses the championship length (Volleyball: BO5).
     */
    function matchBestOfForSchedule($conn, array $matchRow, array $profile, bool $isFinal = false): int
    {
        $bestOf = (int)($matchRow['best_of'] ?? 0);
        if ($bestOf <= 0 && !empty($matchRow['stage_id'])) {
            $stmt = @$conn->prepare("SELECT best_of FROM tbl_league_stage WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stageId = (int)$matchRow['stage_id'];
                $stmt->bind_param('i', $stageId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $bestOf = (int)($row['best_of'] ?? 0);
            }
        }
        if ($bestOf <= 0) $bestOf = sportProfileSeriesLength($profile, $isFinal);
        return max(1, $bestOf);
    }
}

if (!function_exists('matchIsChampionship')) {
    /** Is this match the championship/deciding game of its bracket? */
    function matchIsChampionship(array $matchRow): bool
    {
        $seedA = strtoupper(trim((string)($matchRow['seed_a'] ?? '')));
        $seedB = strtoupper(trim((string)($matchRow['seed_b'] ?? '')));
        if ($seedA !== '' && $seedB !== '' && strpos($seedA, 'W ') === 0 && strpos($seedB, 'W ') === 0) return true;
        $stage = strtolower((string)($matchRow['stage'] ?? ''));
        return $stage === 'final' || $stage === 'finals' || $stage === 'championship';
    }
}

if (!function_exists('saveMatchSets')) {
    /**
     * Replace the stored sets for a match.
     * @param array<int,array{0:int,1:int}> $sets [[aPoints,bPoints], ...]
     */
    function saveMatchSets($conn, int $scheduleId, int $leagueId, array $sets, int $decidingSetNo = 0): void
    {
        deleteMatchSets($conn, $scheduleId);
        if (empty($sets)) return;

        $stmt = $conn->prepare("INSERT INTO tbl_match_set (schedule_id, league_id, set_no, a_points, b_points, is_deciding) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) return;
        $setNo = 0;
        foreach ($sets as $pair) {
            $setNo++;
            $a = (int)($pair[0] ?? 0);
            $b = (int)($pair[1] ?? 0);
            $deciding = ($decidingSetNo > 0 && $setNo === $decidingSetNo) ? 1 : 0;
            $stmt->bind_param('iiiisi', $scheduleId, $leagueId, $setNo, $a, $b, $deciding);
            $stmt->execute();
        }
        $stmt->close();
    }
}

if (!function_exists('formatMatchSets')) {
    /** "25-20, 22-25, 25-18" (display only). */
    function formatMatchSets(array $sets): string
    {
        $parts = [];
        foreach ($sets as $set) {
            $parts[] = (int)$set['a_points'] . '-' . (int)$set['b_points'];
        }
        return implode(', ', $parts);
    }
}

if (!function_exists('summarizeSets')) {
    /**
     * Sets won / total points from the set rows.
     * @return array{sets_a:int,sets_b:int,points_a:int,points_b:int,sets_played:int,winner:string,deciding_set:int}
     */
    function summarizeSets(array $sets, int $bestOf): array
    {
        $setsA = 0; $setsB = 0; $pointsA = 0; $pointsB = 0;
        foreach ($sets as $pair) {
            $a = is_array($pair) ? (int)($pair['a_points'] ?? $pair[0] ?? 0) : 0;
            $b = is_array($pair) ? (int)($pair['b_points'] ?? $pair[1] ?? 0) : 0;
            $pointsA += $a; $pointsB += $b;
            if ($a > $b) $setsA++; elseif ($b > $a) $setsB++;
        }
        $needed = max(1, (int)ceil(max(1, $bestOf) / 2));
        $winner = $setsA >= $needed ? 'a' : ($setsB >= $needed ? 'b' : '');
        return [
            'sets_a'       => $setsA,
            'sets_b'       => $setsB,
            'points_a'     => $pointsA,
            'points_b'     => $pointsB,
            'sets_played'  => count($sets),
            'winner'       => $winner,
            'deciding_set' => max(1, (int)$bestOf),
        ];
    }
}
