<?php use NetArz\WhmcsAi\Balance; use NetArz\WhmcsAi\View; ?>
<link rel="stylesheet" href="<?= $e(View::asset('admin.css')) ?>">
<div class="ntz ntz-widget" dir="<?= \NetArz\WhmcsAi\Lang::isRtl() ? 'rtl' : 'ltr' ?>">
    <?php if (! $hasKey): ?>
        <p class="ntz-muted"><?= $t('widget_no_key') ?></p>
        <a class="ntz-btn is-primary is-sm" href="addonmodules.php?module=netarz_ai&amp;tab=settings"><?= $icon('settings', 14) ?> <?= $t('setup_open_settings') ?></a>
    <?php else: ?>
        <div class="ntz-widget-row">
            <div>
                <small class="ntz-muted"><?= $t('dash_balance') ?></small>
                <?php if ($balance && $balance['ok']): ?>
                    <div class="ntz-big<?= $balance['usd'] <= (float) \NetArz\WhmcsAi\Settings::get('min_balance') * 2 ? ' is-error' : '' ?>"><?= $e($balance['usd_display'] ?: '$'.number_format($balance['usd'], 2)) ?></div>
                    <small class="ntz-muted"><?= $t('dash_toman', ['amount' => \NetArz\WhmcsAi\Lang::digits(number_format($balance['toman']))]) ?></small>
                <?php else: ?>
                    <div class="ntz-big is-error">—</div>
                    <small class="ntz-error-text"><?= $e($balance['error'] ?? '') ?></small>
                <?php endif; ?>
            </div>
            <a class="ntz-btn is-primary is-sm" href="<?= $e(Balance::topupUrl('home-widget')) ?>" target="_blank" rel="noopener"><?= $icon('circle-dollar-sign', 14) ?> <?= $t('dash_topup') ?></a>
        </div>
        <div class="ntz-widget-stats">
            <div><strong>$<?= number_format($today['cost'], 4) ?></strong><small><?= $t('dash_today') ?><?= $budget > 0 ? ' / $'.number_format($budget, 2) : '' ?></small></div>
            <div><strong><?= (int) $today['calls'] ?></strong><small><?= $t('widget_calls') ?></small></div>
            <div class="<?= $waiting > 0 ? 'is-alert' : '' ?>"><strong><?= (int) $waiting ?></strong><small><?= $t('widget_waiting') ?></small></div>
        </div>
        <a class="ntz-link" href="addonmodules.php?module=netarz_ai"><?= $t('widget_open') ?></a>
    <?php endif; ?>
</div>
