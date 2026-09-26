<?php
use NetArz\WhmcsAi\Lang;
use NetArz\WhmcsAi\View;

$lastOutcome = $last ? ($last->outcome ?: $last->status) : '';
?>
<link rel="stylesheet" href="<?= $e(View::asset('admin.css')) ?>">
<div class="ntz ntz-ticket-panel" dir="<?= Lang::isRtl() ? 'rtl' : 'ltr' ?>" data-ticket-panel
     data-endpoint="<?= $e($endpoint) ?>" data-csrf="<?= $e($csrf) ?>" data-ticket="<?= (int) $ticketId ?>">
    <div class="ntz-tp-head">
        <span class="ntz-logo is-sm"><?= $icon('sparkles', 15) ?></span>
        <strong><?= $t('tp_title') ?></strong>
        <?php if ($last): ?>
            <span class="ntz-pill is-<?= $e($lastOutcome) ?>" data-tp-outcome><?= $t('outcome_'.$lastOutcome) ?></span>
        <?php endif; ?>
        <span class="ntz-tp-spacer"></span>
        <button type="button" class="ntz-btn is-sm" data-tp="generate"><?= $icon('wand-sparkles', 14) ?> <span><?= $draft ? $t('tp_regenerate') : $t('tp_generate') ?></span></button>
        <button type="button" class="ntz-btn is-sm" data-tp="pause" data-paused="<?= $paused ? '1' : '0' ?>">
            <?= $icon($paused ? 'play' : 'pause', 14) ?> <span><?= $paused ? $t('tp_resume') : $t('tp_pause') ?></span>
        </button>
    </div>

    <div class="ntz-tp-draft" data-tp-draft<?= $draft ? '' : ' hidden' ?>>
        <div class="ntz-tp-meta">
            <span data-tp-conf><?= $draft ? $t('confidence').': '.(int) $draft->confidence.'%' : '' ?></span>
            <span data-tp-reason class="ntz-muted"><?= $draft && $draft->reason !== '' ? $e(\NetArz\WhmcsAi\Lang::reason($draft->reason)) : '' ?></span>
        </div>
        <div class="ntz-tp-text" data-tp-text dir="auto"><?= $draft ? nl2br($e($draft->draft)) : '' ?></div>
        <div class="ntz-row">
            <button type="button" class="ntz-btn is-primary is-sm" data-tp="use" data-id="<?= $draft ? (int) $draft->id : 0 ?>"><?= $icon('copy', 14) ?> <?= $t('tp_use') ?></button>
            <button type="button" class="ntz-btn is-sm" data-tp="dismiss" data-id="<?= $draft ? (int) $draft->id : 0 ?>"><?= $icon('x', 14) ?> <?= $t('tp_dismiss') ?></button>
        </div>
    </div>
    <p class="ntz-muted ntz-small" data-tp-status><?= $paused ? $t('tp_paused_note') : ($draft ? '' : $t('tp_none')) ?></p>
</div>
<script type="application/json" data-tp-strings><?= json_encode([
    'working' => Lang::get('tp_working'),
    'inserted' => Lang::get('tp_inserted'),
    'confidence' => Lang::get('confidence'),
    'pause' => Lang::get('tp_pause'),
    'resume' => Lang::get('tp_resume'),
    'paused_note' => Lang::get('tp_paused_note'),
    'regenerate' => Lang::get('tp_regenerate'),
    'nothing' => Lang::get('tp_nothing'),
    'error' => Lang::get('err_generic'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= $e(View::asset('ticket-panel.js')) ?>" defer></script>
