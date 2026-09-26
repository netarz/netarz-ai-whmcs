<section class="ntz-inbox" data-inbox>
    <aside class="ntz-threads">
        <div class="ntz-threads-head">
            <div class="ntz-seg" role="tablist">
                <button type="button" class="is-active" data-filter="open"><?= $t('inbox_open') ?></button>
                <button type="button" data-filter="waiting"><?= $t('inbox_waiting') ?></button>
                <button type="button" data-filter="closed"><?= $t('inbox_closed') ?></button>
                <button type="button" data-filter="all"><?= $t('inbox_all') ?></button>
            </div>
        </div>
        <ul class="ntz-thread-list" data-thread-list aria-live="polite">
            <li class="ntz-skeleton"></li><li class="ntz-skeleton"></li><li class="ntz-skeleton"></li>
        </ul>
    </aside>

    <div class="ntz-convo" data-convo>
        <div class="ntz-convo-empty" data-convo-empty>
            <?= $icon('message-circle', 40) ?>
            <p><?= $t('inbox_pick') ?></p>
        </div>

        <div class="ntz-convo-inner" data-convo-inner hidden>
            <header class="ntz-convo-head">
                <button type="button" class="ntz-icon-btn ntz-back" data-action="back" title="<?= $t('back') ?>"><?= $icon('chevron-down', 18) ?></button>
                <div class="ntz-convo-who">
                    <strong data-convo-name></strong>
                    <small data-convo-meta></small>
                </div>
                <div class="ntz-convo-actions">
                    <span class="ntz-pill" data-convo-mode></span>
                    <button type="button" class="ntz-btn is-sm" data-action="takeover" title="<?= $t('inbox_takeover_hint') ?>"><?= $icon('hand', 15) ?> <?= $t('inbox_takeover') ?></button>
                    <button type="button" class="ntz-btn is-sm" data-action="handback" title="<?= $t('inbox_handback_hint') ?>"><?= $icon('bot', 15) ?> <?= $t('inbox_handback') ?></button>
                    <button type="button" class="ntz-btn is-sm" data-action="to-ticket"><?= $icon('ticket', 15) ?> <?= $t('inbox_to_ticket') ?></button>
                    <button type="button" class="ntz-btn is-sm" data-action="close"><?= $icon('check', 15) ?> <?= $t('inbox_close') ?></button>
                    <button type="button" class="ntz-icon-btn is-danger" data-action="delete" title="<?= $t('inbox_delete') ?>"><?= $icon('trash-2', 15) ?></button>
                </div>
            </header>
            <div class="ntz-handoff" data-convo-handoff hidden><?= $icon('triangle-alert', 16) ?> <span></span></div>
            <ol class="ntz-messages" data-messages aria-live="polite"></ol>
            <form class="ntz-composer" data-composer>
                <textarea rows="2" maxlength="8000" placeholder="<?= $t('inbox_placeholder') ?>" data-composer-input></textarea>
                <button type="submit" class="ntz-btn is-primary" title="<?= $t('send') ?>"><?= $icon('send', 16) ?> <span><?= $t('send') ?></span></button>
            </form>
            <p class="ntz-muted ntz-small ntz-composer-hint"><?= $t('inbox_composer_hint') ?></p>
        </div>
    </div>
</section>
<script type="application/json" data-inbox-strings><?= json_encode([
    'ai' => \NetArz\WhmcsAi\Lang::get('mode_ai_short'),
    'human' => \NetArz\WhmcsAi\Lang::get('mode_human_short'),
    'waiting' => \NetArz\WhmcsAi\Lang::get('mode_waiting_short'),
    'closed' => \NetArz\WhmcsAi\Lang::get('inbox_closed'),
    'empty' => \NetArz\WhmcsAi\Lang::get('inbox_empty'),
    'visitor' => \NetArz\WhmcsAi\Lang::get('visitor'),
    'client' => \NetArz\WhmcsAi\Lang::get('client'),
    'guest' => \NetArz\WhmcsAi\Lang::get('guest'),
    'seen' => \NetArz\WhmcsAi\Lang::get('seen'),
    'online' => \NetArz\WhmcsAi\Lang::get('inbox_online'),
    'confirm_delete' => \NetArz\WhmcsAi\Lang::get('inbox_confirm_delete'),
    'ticket_done' => \NetArz\WhmcsAi\Lang::get('inbox_ticket_done'),
    'handoff' => \NetArz\WhmcsAi\Lang::get('inbox_handoff_reason'),
    'confidence' => \NetArz\WhmcsAi\Lang::get('confidence'),
    'error' => \NetArz\WhmcsAi\Lang::get('err_generic'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
