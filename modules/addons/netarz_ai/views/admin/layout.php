<?php
use NetArz\WhmcsAi\Lang;
use NetArz\WhmcsAi\View;

$tabs = [
    'dashboard' => ['activity', 'tab_dashboard'],
    'inbox' => ['message-circle', 'tab_inbox'],
    'tickets' => ['ticket', 'tab_tickets'],
    'knowledge' => ['book-open', 'tab_knowledge'],
    'settings' => ['settings', 'tab_settings'],
    'logs' => ['clock', 'tab_logs'],
];
?>
<link rel="stylesheet" href="<?= $e(View::asset('admin.css')) ?>">
<div class="ntz" dir="<?= Lang::isRtl() ? 'rtl' : 'ltr' ?>" lang="<?= Lang::code() ?>"
     data-endpoint="<?= $e($link) ?>&amp;ajax=" data-csrf="<?= $e($csrf) ?>" data-tab="<?= $e($tab) ?>">
    <header class="ntz-top">
        <div class="ntz-brand">
            <span class="ntz-logo"><?= $icon('sparkles', 20) ?></span>
            <div>
                <strong><?= $t('module_title') ?></strong>
                <small><?= $t('module_subtitle') ?></small>
            </div>
        </div>
        <nav class="ntz-tabs" role="tablist">
            <?php foreach ($tabs as $key => $meta): ?>
                <a href="<?= $e($link) ?>&amp;tab=<?= $key ?>" class="ntz-tab<?= $tab === $key ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $tab === $key ? 'true' : 'false' ?>">
                    <?= $icon($meta[0], 16) ?><span><?= $t($meta[1]) ?></span>
                    <?php if ($key === 'inbox' && $waiting > 0): ?><em class="ntz-badge" data-waiting><?= (int) $waiting ?></em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <?php if ($flash): ?>
        <div class="ntz-flash is-<?= $e($flash['type']) ?>" role="status"><?= $icon($flash['type'] === 'success' ? 'check' : 'triangle-alert', 16) ?> <?= $e($flash['text']) ?></div>
    <?php endif; ?>

    <?php if (! $hasKey && $tab !== 'settings'): ?>
        <div class="ntz-setup">
            <div class="ntz-setup-icon"><?= $icon('zap', 26) ?></div>
            <div>
                <h3><?= $t('setup_title') ?></h3>
                <p><?= $t('setup_text') ?></p>
                <div class="ntz-row">
                    <a class="ntz-btn is-primary" href="https://netarz.ir/ai" target="_blank" rel="noopener"><?= $icon('arrow-up-right', 16) ?> <?= $t('setup_get_key') ?></a>
                    <a class="ntz-btn" href="<?= $e($link) ?>&amp;tab=settings"><?= $icon('settings', 16) ?> <?= $t('setup_open_settings') ?></a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <main class="ntz-body">
        <?= $view('admin/'.$tab) ?>
    </main>

    <footer class="ntz-foot">
        <span>NetArz AI for WHMCS v<?= \NetArz\WhmcsAi\Schema::VERSION ?></span>
        <a href="https://netarz.ir/docs/ai" target="_blank" rel="noopener"><?= $t('foot_docs') ?></a>
        <a href="https://github.com/netarz/netarz-ai-whmcs" target="_blank" rel="noopener">GitHub</a>
        <a href="https://netarz.ir/ai" target="_blank" rel="noopener"><?= $t('foot_panel') ?></a>
    </footer>
</div>
<script src="<?= $e(View::asset('admin.js')) ?>" defer></script>
