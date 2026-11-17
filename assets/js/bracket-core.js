/**
 * Shared bracket primitives
 * -------------------------
 * Geometry, bye detection, ordering and round labels used by both the
 * organizer schedule and the public schedule. HTML templates stay page-specific
 * because the two boards use different themes; only the data rules live here.
 */
(function (global) {
    'use strict';

    function isByeMatch(match) {
        const teamA = Number(match.team_a || 0) || 0;
        const teamB = Number(match.team_b || 0) || 0;
        // A seeded bye always has the surviving team on side A.
        if (teamA > 0 && teamB === 0 && String(match.seed_b || '').toUpperCase() === 'BYE') return true;
        // Auto-collapsed byes are completed one-team rows. Older rows were not
        // stamped with seed_b = 'BYE', so the shape is the reliable fallback.
        const completed = String(match.status || '').toLowerCase() === 'completed';
        return completed && ((teamA === 0) !== (teamB === 0));
    }

    function byeSide(match) {
        if (!isByeMatch(match)) return null;
        return Number(match.team_a || 0) === 0 ? 'a' : 'b';
    }

    function bracketMatchSide(match) {
        return String(match.bracket_side || '').toLowerCase() || 'winners';
    }

    function compareMatches(a, b) {
        const aNode = String(a.node_key || '').trim();
        const bNode = String(b.node_key || '').trim();
        if (aNode !== '' && bNode !== '' && aNode !== bNode) {
            return aNode.localeCompare(bNode, undefined, { numeric: true, sensitivity: 'base' });
        }
        return Number(a.id || 0) - Number(b.id || 0);
    }

    function roundLabel(roundCount, roundNumber, side, format) {
        const fromEnd = roundCount - roundNumber + 1;
        let label;
        if (fromEnd === 1) label = 'Final';
        else if (fromEnd === 2) label = 'Semifinal';
        else if (fromEnd === 3) label = 'Quarterfinal';
        else if (fromEnd === 4) label = 'Round of 16';
        else label = 'Round of ' + Math.pow(2, fromEnd);

        if (side === 'grand_final') return roundNumber <= 1 ? 'Grand Final' : 'Grand Final reset';
        if (side === 'losers') return 'Losers ' + label;
        if (side === 'consolation') return 'Consolation ' + label;
        if (format === 'double_elimination' && side === 'winners') return 'Winners ' + label;
        return label;
    }

    function skeletonSize(entries) {
        let size = 2;
        const target = Math.max(2, Number(entries || 0) || 2);
        while (size < target) size *= 2;
        return size;
    }

    function winnersSizes(size) {
        const sizes = [];
        for (let count = size / 2; count >= 1; count = Math.floor(count / 2)) sizes.push(count);
        return sizes;
    }

    function losersSizes(size) {
        let rounds = 0;
        for (let s = size; s > 1; s = Math.floor(s / 2)) rounds++;
        const sizes = [];
        const total = Math.max(0, 2 * (rounds - 1));
        for (let j = 1; j <= total; j++) {
            const m = Math.floor((j + 1) / 2);
            sizes.push(Math.max(1, Math.floor(size / Math.pow(2, m + 1))));
        }
        return sizes;
    }

    function skeletonMatches(stage, sizes, side, format, counter) {
        const matches = [];
        sizes.forEach(function (count, index) {
            for (let i = 1; i <= count; i++) {
                matches.push({
                    id: ++counter.value,
                    is_skeleton: true,
                    round: index + 1,
                    bracket_side: side,
                    status: 'draft',
                    team_a: 0,
                    team_b: 0,
                    seed_a: '',
                    seed_b: '',
                    stage_name: stage.name,
                    stage_format: format,
                    stage_group_name: ''
                });
            }
        });
        return matches;
    }

    global.LmsBracketCore = {
        isByeMatch: isByeMatch,
        byeSide: byeSide,
        bracketMatchSide: bracketMatchSide,
        compareMatches: compareMatches,
        roundLabel: roundLabel,
        skeletonSize: skeletonSize,
        winnersSizes: winnersSizes,
        losersSizes: losersSizes,
        skeletonMatches: skeletonMatches
    };
})(window);
