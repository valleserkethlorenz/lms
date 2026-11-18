<?php
/**
 * Shared standings presentation helpers
 * -------------------------------------
 * Used by the public standings page and the organizer standings workspace so a
 * stage table, bracket card and format badge look identical in both places.
 * The data itself comes from includes/standingsSections.php.
 */

if (!function_exists('standingsFormatIcon')) {
    function standingsFormatIcon(string $format): string
    {
        switch ($format) {
            case 'round_robin':        return 'bi-table';
            case 'swiss':              return 'bi-shuffle';
            case 'single_elimination': return 'bi-diagram-3-fill';
            case 'double_elimination': return 'bi-diagram-2-fill';
            case 'placement':          return 'bi-award-fill';
            case 'overall':            return 'bi-bar-chart-line-fill';
            default:                   return 'bi-trophy-fill';
        }
    }
}

if (!function_exists('standingsTeamImage')) {
    /**
     * Public URL for a team image. The file check uses the raw basename; only
     * the URL is encoded, so uploads with spaces no longer fall back.
     */
    function standingsTeamImage($rawImage, string $assetPrefix = ''): string
    {
        $rawImage = trim((string)$rawImage);
        if ($rawImage !== '' && class_exists('StorageService') && StorageService::isCloudinary($rawImage)) {
            return $rawImage;
        }
        $file = basename($rawImage);
        if ($file !== '' && is_file(__DIR__ . '/../assets/uploads/' . $file)) {
            return $assetPrefix . 'assets/uploads/' . rawurlencode($file);
        }
        return $assetPrefix . 'assets/images/default-league.svg';
    }
}

if (!function_exists('standingsDateLabel')) {
    function standingsDateLabel($date): string
    {
        $date = trim((string)$date);
        if ($date === '' || $date === '0000-00-00') return '';
        $timestamp = strtotime($date);
        return $timestamp ? date('M j, Y', $timestamp) : '';
    }
}

if (!function_exists('standingsTimeLabel')) {
    function standingsTimeLabel($time): string
    {
        $time = trim((string)$time);
        if ($time === '') return '';
        $timestamp = strtotime($time);
        return $timestamp ? date('g:i A', $timestamp) : $time;
    }
}

if (!function_exists('renderStandingsEmpty')) {
    function renderStandingsEmpty(string $title, string $message, string $icon = 'bi-hourglass-split'): string
    {
        return '<div class="lp-stage-empty"><i class="bi ' . htmlspecialchars($icon, ENT_QUOTES) . '"></i>'
            . '<div><b>' . htmlspecialchars($title) . '</b>'
            . '<p>' . htmlspecialchars($message) . '</p></div></div>';
    }
}

if (!function_exists('standingsProgressBadge')) {
    function standingsProgressBadge($status): string
    {
        $status = trim((string)$status);
        $label = $status !== '' ? $status : 'Undecided';
        $class = $status === 'Qualified'
            ? 'is-qualified'
            : ($status === 'Eliminated' ? 'is-eliminated' : 'is-neutral');
        return '<span class="lp-standings-progress ' . $class . '">' . htmlspecialchars($label) . '</span>';
    }
}

if (!function_exists('renderStandingsStageHeader')) {
    /**
     * Neutral stage header used inside a public .lp-panel or organizer
     * .activity-section.
     */
    function renderStandingsStageHeader(array $section, array $options = []): string
    {
        $title       = (string)($section['stage_name'] ?? 'Standings');
        $groupName   = (string)($section['group_name'] ?? '');
        $formatLabel = (string)($section['format_label'] ?? '');
        $caption     = (string)($section['caption'] ?? '');
        $statusLabel = (string)($section['status_label'] ?? '');
        $statusClass = (string)($section['status_class'] ?? 'is-neutral');
        $teamCount   = (int)($section['team_count'] ?? 0);
        $showStatus  = $options['show_status'] ?? true;
        $showFormat  = $options['show_format'] ?? true;

        $html  = '<div class="lp-stage-head">';
        $html .= '<div class="lp-stage-heading">';
        $html .= '<span class="lp-stage-icon"><i class="bi ' . htmlspecialchars(standingsFormatIcon((string)($section['stage_format'] ?? '')), ENT_QUOTES) . '"></i></span>';
        $html .= '<div><h2 class="lp-stage-title">' . htmlspecialchars($title);
        if ($groupName !== '') {
            $html .= ' <span class="lp-stage-group-name">' . htmlspecialchars($groupName) . '</span>';
        }
        $html .= '</h2>';
        if ($caption !== '') {
            $html .= '<p class="lp-stage-caption">' . htmlspecialchars($caption) . '</p>';
        }
        $html .= '</div></div>';
        $html .= '<div class="lp-stage-badges">';
        if ($showFormat && $formatLabel !== '') {
            $html .= '<span class="lp-stage-format">' . htmlspecialchars($formatLabel) . '</span>';
        }
        if ($showStatus && $statusLabel !== '') {
            $html .= '<span class="lp-stage-status ' . htmlspecialchars($statusClass, ENT_QUOTES) . '">' . htmlspecialchars($statusLabel) . '</span>';
        }
        if ($teamCount > 0) {
            $html .= '<span class="lp-stage-count"><i class="bi bi-people"></i>' . $teamCount . ' team' . ($teamCount === 1 ? '' : 's') . '</span>';
        }
        $html .= '</div></div>';

        return $html;
    }
}

if (!function_exists('renderStandingsMatchCard')) {
    /**
     * One bracket / placement match card. Draft rows keep their draw but hide
     * scores, dates and venues until the organizer publishes the fixture.
     */
    function renderStandingsMatchCard(array $match, array $options = []): string
    {
        $showMeta  = $options['show_meta'] ?? true;
        $showGroup = $options['show_group'] ?? false;

        $teamAName = trim((string)($match['team_a_name'] ?? ''));
        $teamBName = trim((string)($match['team_b_name'] ?? ''));
        $seedA     = trim((string)($match['seed_a'] ?? ''));
        $seedB     = trim((string)($match['seed_b'] ?? ''));
        $teamAId   = (int)($match['team_a'] ?? 0);
        $teamBId   = (int)($match['team_b'] ?? 0);
        $winnerId  = (int)($match['winner'] ?? 0);
        $winType   = trim((string)($match['win_type'] ?? ''));
        $statusRaw = strtolower(trim((string)($match['status'] ?? '')));
        $published = !empty($match['is_published']) || !empty($match['published_at']);
        $completed = $published && $statusRaw === 'completed';

        $isBye = $seedA === 'BYE' || $seedB === 'BYE'
            || ($completed && ($teamAId === 0 xor $teamBId === 0));
        $isTbd = !$isBye && $teamAId === 0 && $teamBId === 0;

        $labelA = $teamAName !== '' ? $teamAName : 'TBD';
        $labelB = $teamBName !== '' ? $teamBName : 'TBD';
        if ($isBye) {
            if ($teamAId === 0) $labelA = 'BYE';
            if ($teamBId === 0) $labelB = 'BYE';
        }

        $aWin  = $completed && $winnerId > 0 && $winnerId === $teamAId;
        $bWin  = $completed && $winnerId > 0 && $winnerId === $teamBId;
        $isDraw = $completed && $winnerId === 0 && $teamAId > 0 && $teamBId > 0;

        $classes = ['lp-bracket-match'];
        if ($completed) $classes[] = 'is-complete';
        if ($isBye) $classes[] = 'is-bye';
        if ($isTbd) $classes[] = 'is-tbd';
        if (!$published) $classes[] = 'is-draft';
        if (in_array($statusRaw, ['cancelled', 'postponed'], true)) $classes[] = 'is-cancelled';

        if ($isBye) {
            $statusLabel = 'Bye';
        } elseif (in_array($statusRaw, ['cancelled', 'postponed'], true)) {
            $statusLabel = ucfirst($statusRaw);
        } elseif ($completed) {
            $statusLabel = $winType === 'DoubleForfeit' ? 'Double forfeit' : ($isDraw ? 'Draw' : 'Final');
        } elseif (!$published) {
            $statusLabel = 'To be played';
        } else {
            $statusLabel = ucfirst($statusRaw !== '' ? $statusRaw : 'Scheduled');
        }

        $meta = [];
        if ($showMeta && $published) {
            $date  = standingsDateLabel($match['date'] ?? '');
            $time  = standingsTimeLabel($match['time'] ?? '');
            $venue = trim((string)($match['venue'] ?? ''));
            $when  = trim($date . ($date !== '' && $time !== '' ? ' · ' : '') . $time);
            if ($when !== '') $meta[] = $when;
            if ($venue !== '') $meta[] = $venue;
        }
        if ($showGroup && trim((string)($match['stage_group_name'] ?? '')) !== '') {
            $meta[] = trim((string)$match['stage_group_name']);
        }
        if ($completed && in_array($winType, ['Forfeit', 'Walkover', 'Decision', 'DoubleForfeit'], true)) {
            $meta[] = $winType;
        }

        $scoreA = $completed && !$isBye ? (int)($match['a_score'] ?? 0) : null;
        $scoreB = $completed && !$isBye ? (int)($match['b_score'] ?? 0) : null;

        $aria = $labelA . ' vs ' . $labelB;
        if ($completed && !$isBye) $aria .= ' — ' . $scoreA . ' to ' . $scoreB;

        $html  = '<div class="' . implode(' ', $classes) . '" data-team="' . htmlspecialchars(strtolower($labelA . ' ' . $labelB), ENT_QUOTES) . '" aria-label="' . htmlspecialchars($aria, ENT_QUOTES) . '">';
        $html .= '<div class="lp-bracket-team' . ($aWin ? ' is-winner' : '') . ($isDraw ? ' is-draw' : '') . '">';
        if ($seedA !== '' && !$isBye) $html .= '<span class="lp-bracket-seed">' . htmlspecialchars($seedA) . '</span>';
        $html .= '<span class="lp-bracket-name">' . htmlspecialchars($labelA) . '</span>';
        if ($scoreA !== null) $html .= '<span class="lp-bracket-score">' . $scoreA . '</span>';
        $html .= '</div>';

        $html .= '<div class="lp-bracket-team' . ($bWin ? ' is-winner' : '') . ($isDraw ? ' is-draw' : '') . '">';
        if ($seedB !== '' && !$isBye) $html .= '<span class="lp-bracket-seed">' . htmlspecialchars($seedB) . '</span>';
        $html .= '<span class="lp-bracket-name">' . htmlspecialchars($labelB) . '</span>';
        if ($scoreB !== null) $html .= '<span class="lp-bracket-score">' . $scoreB . '</span>';
        $html .= '</div>';

        $html .= '<div class="lp-bracket-foot"><span class="lp-bracket-state">' . htmlspecialchars($statusLabel) . '</span>';
        if ($meta) {
            $html .= '<span class="lp-bracket-meta">' . htmlspecialchars(implode(' · ', $meta)) . '</span>';
        }
        $html .= '</div></div>';

        return $html;
    }
}

if (!function_exists('renderStandingsBracket')) {
    /** Full bracket board for one stage (winners / losers / grand final). */
    function renderStandingsBracket(array $section): string
    {
        $sides = $section['sides'] ?? [];
        if (empty($sides)) {
            $entries = (int)($section['entries'] ?? 0);
            $message = $entries > 0
                ? 'Expected entrants: ' . $entries . '. The draw appears here once the organizer generates it.'
                : 'The draw appears here once the organizer generates it.';
            return renderStandingsEmpty('Draw not generated yet', $message, 'bi-diagram-3');
        }

        $showSideHead = count($sides) > 1;
        $html = '<div class="lp-bracket-board">';
        foreach ($sides as $side) {
            $rounds = $side['rounds'] ?? [];
            if (empty($rounds)) continue;
            $sideKey = (string)($side['key'] ?? 'winners');
            $html .= '<div class="lp-bracket-side is-' . htmlspecialchars($sideKey, ENT_QUOTES) . '">';
            if ($showSideHead || $sideKey !== 'winners') {
                $html .= '<div class="lp-bracket-side-head"><h3>' . htmlspecialchars((string)($side['label'] ?? 'Bracket')) . '</h3>'
                    . '<span>' . (int)($side['match_count'] ?? 0) . ' match' . ((int)($side['match_count'] ?? 0) === 1 ? '' : 'es') . '</span></div>';
            }
            $html .= '<div class="lp-bracket-scroll"><div class="lp-bracket-rounds">';
            foreach ($rounds as $round) {
                $html .= '<div class="lp-bracket-round">';
                $html .= '<div class="lp-bracket-round-head">' . htmlspecialchars((string)($round['label'] ?? 'Round')) . '</div>';
                foreach (($round['matches'] ?? []) as $match) {
                    $html .= renderStandingsMatchCard($match);
                }
                $html .= '</div>';
            }
            $html .= '</div></div></div>';
        }
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('renderStandingsPlacement')) {
    /** Placement-match board for a placement stage. */
    function renderStandingsPlacement(array $section): string
    {
        $matches = $section['matches'] ?? [];
        if (empty($matches)) {
            return renderStandingsEmpty('No placement matches yet', 'The placement match appears once the semifinals are decided.', 'bi-award');
        }

        $html = '<div class="lp-placement-grid">';
        foreach ($matches as $match) {
            $round = (int)($match['round'] ?? 0);
            $html .= '<div class="lp-placement-card">';
            if ($round > 0) {
                $html .= '<div class="lp-placement-round">Round ' . $round . '</div>';
            }
            $html .= renderStandingsMatchCard($match, ['show_group' => true]);
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('renderStandingsTable')) {
    /**
     * Shared standings table. Pages pass row-action callbacks as extra columns.
     *
     * Options:
     *   table_class, table_id, show_sets, show_progression, extra_headers,
     *   extra_cells(callable), asset_prefix, empty_title, empty_message,
     *   caption, data_attrs, row_group, row_class
     */
    function renderStandingsTable(array $rows, array $options = []): string
    {
        $tableClass      = (string)($options['table_class'] ?? 'lp-table');
        $tableId         = (string)($options['table_id'] ?? '');
        $showSets        = !empty($options['show_sets']);
        $showProgression = $options['show_progression'] ?? true;
        $extraHeaders    = (array)($options['extra_headers'] ?? []);
        $extraCells      = $options['extra_cells'] ?? null;
        $assetPrefix     = (string)($options['asset_prefix'] ?? '');
        $emptyTitle      = (string)($options['empty_title'] ?? 'No teams yet');
        $emptyMessage    = (string)($options['empty_message'] ?? 'Teams appear here once they are placed in this table.');
        $caption         = (string)($options['caption'] ?? '');
        $dataAttrs       = (string)($options['data_attrs'] ?? '');
        $rowGroup        = (string)($options['row_group'] ?? '');
        $rowClass        = (string)($options['row_class'] ?? '');

        if (empty($rows)) {
            return renderStandingsEmpty($emptyTitle, $emptyMessage);
        }

        $html = '<div class="lp-table-wrap"><table class="' . htmlspecialchars($tableClass, ENT_QUOTES) . '"'
            . ($tableId !== '' ? ' id="' . htmlspecialchars($tableId, ENT_QUOTES) . '"' : '')
            . ($dataAttrs !== '' ? ' ' . $dataAttrs : '') . '>';
        if ($caption !== '') {
            $html .= '<caption class="visually-hidden">' . htmlspecialchars($caption) . '</caption>';
        }

        $html .= '<thead><tr>';
        $html .= '<th scope="col" class="lp-col-num">#</th>';
        $html .= '<th scope="col">Team</th>';
        $html .= '<th scope="col" class="text-center" title="Matches played">MP</th>';
        $html .= '<th scope="col" class="text-center" title="Wins">W</th>';
        $html .= '<th scope="col" class="text-center" title="Draws">D</th>';
        $html .= '<th scope="col" class="text-center" title="Losses">L</th>';
        $html .= '<th scope="col" class="text-center" title="Points">Pts</th>';
        if ($showSets) {
            $html .= '<th scope="col" class="text-center" title="Sets won">SW</th>';
            $html .= '<th scope="col" class="text-center" title="Sets lost">SL</th>';
            $html .= '<th scope="col" class="text-center" title="Set ratio">Set ratio</th>';
        }
        $html .= '<th scope="col" class="text-center" title="' . ($showSets ? 'Points won' : 'Points for') . '">' . ($showSets ? 'PW' : 'PF') . '</th>';
        $html .= '<th scope="col" class="text-center" title="' . ($showSets ? 'Points lost' : 'Points against') . '">' . ($showSets ? 'PL' : 'PA') . '</th>';
        $html .= '<th scope="col" class="text-center" title="Points difference">Diff</th>';
        if ($showProgression) {
            $html .= '<th scope="col" class="text-center"><i class="bi bi-award me-1"></i>Progression</th>';
        }
        foreach ($extraHeaders as $header) {
            $header = is_array($header) ? $header : ['label' => (string)$header];
            $headerClass = trim((string)($header['class'] ?? ''));
            $headerStyle = trim((string)($header['style'] ?? ''));
            $headerTitle = trim((string)($header['title'] ?? ''));
            $headerIcon  = trim((string)($header['icon'] ?? ''));
            $html .= '<th scope="col"'
                . ($headerClass !== '' ? ' class="' . htmlspecialchars($headerClass, ENT_QUOTES) . '"' : '')
                . ($headerStyle !== '' ? ' style="' . htmlspecialchars($headerStyle, ENT_QUOTES) . '"' : '')
                . ($headerTitle !== '' ? ' title="' . htmlspecialchars($headerTitle, ENT_QUOTES) . '"' : '')
                . '>'
                . ($headerIcon !== '' ? '<i class="bi ' . htmlspecialchars($headerIcon, ENT_QUOTES) . ' me-1"></i>' : '')
                . htmlspecialchars((string)($header['label'] ?? ''))
                . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        $defaultImage = htmlspecialchars($assetPrefix . 'assets/images/default-league.svg', ENT_QUOTES);
        foreach ($rows as $index => $row) {
            $position = (int)($row['rank'] ?? ($index + 1));
            $rankCls  = ($position >= 1 && $position <= 3) ? ' is-' . $position : '';
            $teamName = trim((string)($row['team_name'] ?? ''));
            if ($teamName === '') $teamName = 'Team';
            $teamNameSafe = htmlspecialchars($teamName);
            $teamImage    = htmlspecialchars(standingsTeamImage($row['team_image'] ?? '', $assetPrefix), ENT_QUOTES);

            $played = (int)($row['played'] ?? 0);
            $wins   = (int)($row['wins'] ?? 0) + (int)($row['forfeit_wins'] ?? 0);
            $draws  = (int)($row['draws'] ?? 0);
            $losses = (int)($row['losses'] ?? 0) + (int)($row['forfeit_losses'] ?? 0) + (int)($row['double_forfeits'] ?? 0);
            $points = (int)($row['points'] ?? 0);
            $pointsFor     = (int)($row['points_for'] ?? 0);
            $pointsAgainst = (int)($row['points_against'] ?? 0);
            $difference    = $pointsFor - $pointsAgainst;
            $diffClass     = $difference > 0 ? 'is-pos' : ($difference < 0 ? 'is-neg' : 'is-zero');
            $diffText      = ($difference > 0 ? '+' : '') . $difference;

            $rowGroupValue = trim((string)($row['group_name'] ?? $rowGroup));
            $html .= '<tr' . ($rowClass !== '' ? ' class="' . htmlspecialchars($rowClass, ENT_QUOTES) . '"' : '')
                . ' data-team="' . htmlspecialchars(strtolower($teamName), ENT_QUOTES) . '"'
                . ($rowGroupValue !== '' ? ' data-group="' . htmlspecialchars($rowGroupValue, ENT_QUOTES) . '"' : '')
                . '>';
            $html .= '<td class="lp-col-num"><span class="lp-rank' . $rankCls . '">' . $position . '</span></td>';
            $html .= '<td><span class="lp-standings-team"><img src="' . $teamImage . '" alt="" loading="lazy" onerror="this.onerror=null;this.src=\'' . $defaultImage . '\';"><span class="lp-standings-team-name">' . $teamNameSafe . '</span></span></td>';
            $html .= '<td class="text-center lp-num">' . $played . '</td>';
            $html .= '<td class="text-center lp-num">' . $wins . '</td>';
            $html .= '<td class="text-center lp-num">' . $draws . '</td>';
            $html .= '<td class="text-center lp-num">' . $losses . '</td>';
            $html .= '<td class="text-center lp-num lp-pts">' . $points . '</td>';
            if ($showSets) {
                $html .= '<td class="text-center lp-num">' . (int)($row['sets_won'] ?? 0) . '</td>';
                $html .= '<td class="text-center lp-num">' . (int)($row['sets_lost'] ?? 0) . '</td>';
                $html .= '<td class="text-center lp-num">' . number_format((float)($row['set_ratio'] ?? 0), 3) . '</td>';
            }
            $html .= '<td class="text-center lp-num">' . $pointsFor . '</td>';
            $html .= '<td class="text-center lp-num">' . $pointsAgainst . '</td>';
            $html .= '<td class="text-center"><span class="lp-diff ' . $diffClass . '">' . $diffText . '</span></td>';
            if ($showProgression) {
                $html .= '<td class="text-center">' . standingsProgressBadge($row['progression_status'] ?? '') . '</td>';
            }
            if (is_callable($extraCells)) {
                $cells = $extraCells($row, $index);
                if (is_array($cells)) {
                    foreach ($cells as $cell) $html .= (string)$cell;
                }
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }
}
