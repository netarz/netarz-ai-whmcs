<?php
use NetArz\WhmcsAi\Balance;
use NetArz\WhmcsAi\Settings;

$budget = (float) Settings::get('daily_budget');
$maxCost = 0.0;
foreach ($daily as $d) {
    $maxCost = max($maxCost, $d['cost']);
}
$mode = (string) Settings::get('ticket_mode');
?>
<section class="ntz-grid ntz-grid-4">
    <article class="ntz-card ntz-balance<?= $balance && $balance['ok'] && $balance['usd'] <= (float) Settings::get('min_balance') * 2 ? ' is-low' : '' ?>" data-balance-card>
        <div class="ntz-card-head">
            <span class="ntz-card-icon"><?= $icon('wallet', 18) ?></span>
            <span><?= $t('dash_balance') ?></span>
            <button type="button" class="ntz-icon-btn" data-action="refresh-balance" title="<?= $t('refresh') ?>"><?= $icon('refresh-cw', 15) ?></button>
        </div>
        <?php if ($balance && $balance['ok']): ?>
            <div class="ntz-big" data-balance-usd><?= $e($balance['usd_display'] ?: '$'.number_format($balance['usd'], 2)) ?></div>
            <div class="ntz-muted" data-balance-toman><?= $t('dash_toman', ['amount' => \NetArz\WhmcsAi\Lang::digits(number_format($balance['toman']))]) ?></div>
            <a class="ntz-btn is-primary is-sm" href="<?= $e(Balance::TOPUP_URL) ?>" target="_blank" rel="noopener"><?= $icon('circle-dollar-sign', 15) ?> <?= $t('dash_topup') ?></a>
        <?php elseif ($balance): ?>
            <div class="ntz-big is-error">—</div>
            <div class="ntz-error-text"><?= $e($balance['error']) ?></div>
        <?php else: ?>
            <div class="ntz-big">—</div>
            <div class="ntz-muted"><?= $t('dash_no_key') ?></div>
        <?php endif; ?>
    </article>

    <article class="ntz-card">
        <div class="ntz-card-head"><span class="ntz-card-icon"><?= $icon('activity', 18) ?></span><span><?= $t('dash_today') ?></span></div>
        <div class="ntz-big">$<?= number_format($today['cost'], 4) ?></div>
        <div class="ntz-muted"><?= $t('dash_calls', ['n' => $today['calls']]) ?><?= $budget > 0 ? ' · '.$t('dash_budget_of', ['cap' => \NetArz\WhmcsAi\Lang::ltr('$'.number_format($budget, 2))]) : '' ?></div>
        <?php if ($budget > 0): ?>
            <div class="ntz-meter" role="meter" aria-valuemin="0" aria-valuemax="<?= $budget ?>" aria-valuenow="<?= $today['cost'] ?>"><i style="width: <?= min(100, round($today['cost'] / $budget * 100)) ?>%"></i></div>
        <?php endif; ?>
    </article>

    <article class="ntz-card">
        <div class="ntz-card-head"><span class="ntz-card-icon"><?= $icon('message-circle', 18) ?></span><span><?= $t('dash_chats') ?></span></div>
        <div class="ntz-big"><?= (int) $openChats ?></div>
        <div class="ntz-muted"><?= $t('dash_waiting', ['n' => (int) $waiting]) ?></div>
        <a class="ntz-btn is-sm" href="<?= $e($link) ?>&amp;tab=inbox"><?= $icon('inbox', 15) ?> <?= $t('dash_open_inbox') ?></a>
    </article>

    <article class="ntz-card">
        <div class="ntz-card-head"><span class="ntz-card-icon"><?= $icon('ticket', 18) ?></span><span><?= $t('dash_tickets') ?></span></div>
        <div class="ntz-big"><?= (int) $month['ticket'] ?></div>
        <div class="ntz-muted"><?= $t('dash_ticket_mode') ?>: <strong><?= $t('mode_'.$mode) ?></strong></div>
        <div class="ntz-muted"><?= $t('dash_handoffs', ['n' => $month['handoffs']]) ?></div>
    </article>
</section>

<section class="ntz-grid ntz-grid-2">
    <article class="ntz-card">
        <div class="ntz-card-head"><span class="ntz-card-icon"><?= $icon('activity', 18) ?></span><span><?= $t('dash_chart') ?></span></div>
        <div class="ntz-bars" role="img" aria-label="<?= $t('dash_chart') ?>">
            <?php foreach ($daily as $day => $d): $h = $maxCost > 0 ? max(2, round($d['cost'] / $maxCost * 100)) : 2; ?>
                <div class="ntz-bar" title="<?= $e($day) ?> — $<?= number_format($d['cost'], 4) ?> · <?= (int) $d['calls'] ?>">
                    <i style="height: <?= $h ?>%"></i>
                    <span><?= $e(substr($day, 8, 2)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="ntz-muted ntz-small"><?= $t('dash_chart_note') ?></p>
    </article>

    <article class="ntz-card">
        <div class="ntz-card-head"><span class="ntz-card-icon"><?= $icon('ticket', 18) ?></span><span><?= $t('dash_recent') ?></span>
            <a class="ntz-link" href="<?= $e($link) ?>&amp;tab=tickets"><?= $t('see_all') ?></a></div>
        <?php if (! $recentJobs): ?>
            <p class="ntz-empty"><?= $t('dash_recent_empty') ?></p>
        <?php else: ?>
            <ul class="ntz-list">
                <?php foreach ($recentJobs as $job): ?>
                    <li>
                        <a href="supporttickets.php?action=view&amp;id=<?= (int) $job->ticket_id ?>">#<?= (int) $job->ticket_id ?></a>
                        <span class="ntz-pill is-<?= $e($job->outcome ?: $job->status) ?>"><?= $t('outcome_'.($job->outcome ?: $job->status)) ?></span>
                        <?php if ((int) $job->confidence > 0): ?><span class="ntz-muted"><?= (int) $job->confidence ?>%</span><?php endif; ?>
                        <time class="ntz-muted"><?= $e(substr((string) $job->updated_at, 5, 11)) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</section>

<?php if ($balance && $balance['ok']): ?>
<section class="ntz-card ntz-account">
    <div class="ntz-kv"><span><?= $t('dash_project') ?></span><strong><?= $e($balance['project']) ?></strong></div>
    <div class="ntz-kv"><span><?= $t('dash_key') ?></span><strong dir="ltr"><?= $e($balance['key']) ?></strong></div>
    <div class="ntz-kv"><span><?= $t('dash_rpm') ?></span><strong><?= (int) $balance['rpm'] ?></strong></div>
    <div class="ntz-kv"><span><?= $t('dash_model') ?></span><strong dir="ltr"><?= $e(Settings::get('model')) ?></strong></div>
    <div class="ntz-kv"><span><?= $t('dash_month') ?></span><strong>$<?= number_format($month['cost'], 4) ?> · <?= (int) $month['calls'] ?></strong></div>
</section>
<?php endif; ?>
