<?php
/**
 * Roster size helpers
 * -------------------
 * tbl_league.min_player / tbl_league.max_player are the authoritative roster
 * limits for a league. The two values are allowed to be identical, which means
 * the roster size is fixed (e.g. Pickleball doubles: min 2, max 2).
 *
 * A fixed roster must never be rendered as "2–2 players". Every label in the
 * UI goes through the helpers below so a fixed size reads
 * "Exactly 2 players" (and "2" in compact tiles/chips) instead.
 *
 * Conventions kept in sync with includes/eligibilityValidator.php:
 *   - min is clamped to at least 1
 *   - max = 0 means "no upper limit"
 *   - max below min is treated as min (defensive, the forms already block it)
 */

if (!function_exists('normalizeRosterBounds')) {
    /**
     * Normalize a configured roster range.
     *
     * @param mixed $min Configured minimum players.
     * @param mixed $max Configured maximum players (0 = unlimited).
     * @return array{min:int,max:int,fixed:bool,unlimited:bool}
     */
    function normalizeRosterBounds($min, $max): array
    {
        $minimum = max(1, (int)$min);
        $maximum = (int)$max;
        if ($maximum > 0 && $maximum < $minimum) {
            $maximum = $minimum;
        }

        return [
            'min' => $minimum,
            'max' => $maximum,
            'fixed' => $maximum > 0 && $maximum === $minimum,
            'unlimited' => $maximum <= 0,
        ];
    }
}

if (!function_exists('isFixedRosterSize')) {
    /**
     * True when the league requires an exact number of players (min === max).
     */
    function isFixedRosterSize($min, $max): bool
    {
        $bounds = normalizeRosterBounds($min, $max);
        return $bounds['fixed'];
    }
}

if (!function_exists('rosterSizeLabel')) {
    /**
     * Full sentence-style label.
     *   min = max = 2  -> "Exactly 2 players"
     *   min 5, max 15  -> "5–15 players"
     *   min 5, max 0   -> "At least 5 players"
     */
    function rosterSizeLabel($min, $max, string $unit = 'players'): string
    {
        $bounds = normalizeRosterBounds($min, $max);

        if ($bounds['fixed']) {
            return 'Exactly ' . $bounds['min'] . ' ' . $unit;
        }
        if ($bounds['unlimited']) {
            return 'At least ' . $bounds['min'] . ' ' . $unit;
        }

        return $bounds['min'] . '–' . $bounds['max'] . ' ' . $unit;
    }
}

if (!function_exists('rosterSizeShortLabel')) {
    /**
     * Compact label for stat tiles and chips.
     *   fixed      -> "2"
     *   range      -> "5–15"
     *   unlimited  -> "5+"
     */
    function rosterSizeShortLabel($min, $max): string
    {
        $bounds = normalizeRosterBounds($min, $max);

        if ($bounds['unlimited']) {
            return $bounds['min'] . '+';
        }
        if ($bounds['fixed']) {
            return (string)$bounds['min'];
        }

        return $bounds['min'] . '–' . $bounds['max'];
    }
}

if (!function_exists('rosterSizeRequirementText')) {
    /**
     * Lower-case phrase that fits inside a sentence.
     *   fixed      -> "exactly 2 players"
     *   range      -> "5 to 15 players"
     *   unlimited  -> "at least 5 players"
     */
    function rosterSizeRequirementText($min, $max): string
    {
        $bounds = normalizeRosterBounds($min, $max);

        if ($bounds['fixed']) {
            return 'exactly ' . $bounds['min'] . ' player' . ($bounds['min'] === 1 ? '' : 's');
        }
        if ($bounds['unlimited']) {
            return 'at least ' . $bounds['min'] . ' player' . ($bounds['min'] === 1 ? '' : 's');
        }

        return $bounds['min'] . ' to ' . $bounds['max'] . ' players';
    }
}
