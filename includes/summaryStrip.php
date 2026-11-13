<?php
/**
 * Shared summary strip renderer.
 *
 * Renders the same summary cards used on the account directory
 * (app/admin/users.php): one joined white panel whose cells carry a tinted
 * background, a 3px accent bar, an uppercase label, an accent-coloured value
 * and a muted note. Every role page uses this helper so the strips stay
 * identical.
 *
 * The role dashboards keep their own metric strips (ad-metric / cm-metric /
 * og-metric / tr-metric) and are deliberately not part of this component.
 *
 * Styles: includes/summary-strip-styles.php — include once per page.
 *
 * Usage:
 *   require_once __DIR__ . '/../../includes/summaryStrip.php';
 *
 *   renderSummaryStrip([
 *       ['label' => 'Total accounts', 'value' => 128, 'note' => 'Across all roles'],
 *       ['label' => 'Pending review', 'value' => 4,  'note' => 'Awaiting a decision'],
 *   ], 'Account totals');
 *
 * Per card keys:
 *   label  string   Uppercase text label (required)
 *   value  int|str  Big number / short status text (required)
 *   note   string   Muted line under the value
 *   notes  string[] Extra muted lines
 *   link   array{href:string,label:string}  Link rendered after the notes
 *   accent string   Overrides the positional accent colour
 *   tint   string   Overrides the positional tinted background
 *
 * Optional third argument: column count (3 or 5 switch to their own layout).
 */

if (!function_exists('summaryStripPalette')) {
    /**
     * Accent / tint pairs taken from the account directory strip.
     *
     * @return array<int,array{accent:string,tint:string}>
     */
    function summaryStripPalette(): array
    {
        return [
            ['accent' => '#17356f', 'tint' => '#f1f4fa'],
            ['accent' => '#245cbb', 'tint' => '#eff4fd'],
            ['accent' => '#16735e', 'tint' => '#edf7f3'],
            ['accent' => '#7040a2', 'tint' => '#f5effa'],
            ['accent' => '#176585', 'tint' => '#edf6fa'],
        ];
    }
}

if (!function_exists('renderSummaryStrip')) {
    /**
     * Echo the shared summary strip markup.
     *
     * @param array<int,array<string,mixed>> $cards
     */
    function renderSummaryStrip(array $cards, string $ariaLabel = 'Summary totals', int $columns = 0): void
    {
        if (!$cards) {
            return;
        }

        $palette  = summaryStripPalette();
        $columns  = $columns > 0 ? $columns : count($cards);
        $esc      = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $modifier = in_array($columns, [3, 5], true) ? ' is-' . $columns : '';

        echo '<div class="summary-strip' . $modifier . '" role="group" aria-label="' . $esc($ariaLabel) . '">';

        foreach (array_values($cards) as $index => $card) {
            $pair   = $palette[$index % count($palette)];
            $accent = (string) ($card['accent'] ?? $pair['accent']);
            $tint   = (string) ($card['tint'] ?? $pair['tint']);

            $notes = [];
            if (isset($card['note']) && (string) $card['note'] !== '') {
                $notes[] = (string) $card['note'];
            }
            if (!empty($card['notes']) && is_array($card['notes'])) {
                foreach ($card['notes'] as $extraNote) {
                    if ((string) $extraNote !== '') {
                        $notes[] = (string) $extraNote;
                    }
                }
            }

            echo '<div class="summary-item" style="--metric-accent:' . $esc($accent) . ';--metric-tint:' . $esc($tint) . '">';
            echo '<span class="summary-label">' . $esc($card['label'] ?? '') . '</span>';
            echo '<strong class="summary-value">' . $esc($card['value'] ?? '') . '</strong>';
            foreach ($notes as $note) {
                echo '<small class="summary-note">' . $esc($note) . '</small>';
            }
            $link = (isset($card['link']) && is_array($card['link'])) ? $card['link'] : [];
            if (!empty($link['href']) && !empty($link['label'])) {
                echo '<small class="summary-note"><a class="summary-link" href="' . $esc($link['href']) . '">'
                    . $esc($link['label']) . '</a></small>';
            }
            echo '</div>';
        }

        echo '</div>';
    }
}
