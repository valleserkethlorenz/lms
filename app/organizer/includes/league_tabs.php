<?php
$leagueTabLeagueId = (int)($leagueTabLeagueId ?? $id ?? $leagueId ?? 0);
$leagueTabPage = basename($_SERVER['PHP_SELF']);
$leagueTabs = [
    ['page'=>'league_info.php', 'label'=>'Overview'],
    ['page'=>'setup.php', 'label'=>'Setup'],
    ['page'=>'schedule.php', 'label'=>'Schedule'],
    ['page'=>'standing.php', 'label'=>'Standings'],
];
?>
<div class="p-2 league-section-tabs">
    <ul class="nav nav-underline nav-fill border-bottom mb-4">
        <?php foreach ($leagueTabs as $tab): $active = $leagueTabPage === $tab['page']; ?>
            <li class="nav-item">
                <a class="nav-link fw-semibold <?= $active ? 'active text-primary' : 'text-dark' ?>"
                   href="<?= htmlspecialchars($tab['page'], ENT_QUOTES, 'UTF-8') ?>?id=<?= $leagueTabLeagueId ?>">
                    <?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
