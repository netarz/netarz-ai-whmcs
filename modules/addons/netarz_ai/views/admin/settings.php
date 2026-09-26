<?php
$s = $settings;
$switch = function (string $key, string $label, string $hint = '') use ($s, $t) {
    $id = 'ntz-'.$key;

    return '<label class="ntz-switch" for="'.$id.'"><input type="checkbox" id="'.$id.'" name="'.$key.'" value="1"'.((int) $s[$key] === 1 ? ' checked' : '').'>'
        .'<i aria-hidden="true"></i><span><strong>'.$t($label).'</strong>'.($hint !== '' ? '<small>'.$t($hint).'</small>' : '').'</span></label>';
};
$field = function (string $key, string $label, string $hint = '', string $type = 'text', array $attrs = []) use ($s, $e, $t) {
    $id = 'ntz-'.$key;
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' '.$k.'="'.$e($v).'"';
    }

    return '<div class="ntz-field"><label class="ntz-label" for="'.$id.'">'.$t($label).'</label>'
        .'<input class="ntz-input" type="'.$type.'" id="'.$id.'" name="'.$key.'" value="'.$e($s[$key]).'"'.$extra.'>'
        .($hint !== '' ? '<small class="ntz-hint">'.$t($hint).'</small>' : '').'</div>';
};
$select = function (string $key, string $label, array $options, string $hint = '') use ($s, $e, $t) {
    $id = 'ntz-'.$key;
    $html = '<div class="ntz-field"><label class="ntz-label" for="'.$id.'">'.$t($label).'</label><select class="ntz-input" id="'.$id.'" name="'.$key.'">';
    foreach ($options as $value => $text) {
        $html .= '<option value="'.$e($value).'"'.((string) $s[$key] === (string) $value ? ' selected' : '').'>'.$e($text).'</option>';
    }

    return $html.'</select>'.($hint !== '' ? '<small class="ntz-hint">'.$t($hint).'</small>' : '').'</div>';
};
$textarea = function (string $key, string $label, string $hint = '', int $rows = 4, string $placeholder = '') use ($s, $e, $t) {
    $id = 'ntz-'.$key;

    return '<div class="ntz-field"><label class="ntz-label" for="'.$id.'">'.$t($label).'</label>'
        .'<textarea class="ntz-input" id="'.$id.'" name="'.$key.'" rows="'.$rows.'"'.($placeholder !== '' ? ' placeholder="'.$t($placeholder).'"' : '').'>'.$e($s[$key]).'</textarea>'
        .($hint !== '' ? '<small class="ntz-hint">'.$t($hint).'</small>' : '').'</div>';
};
$L = function (string $key) { return \NetArz\WhmcsAi\Lang::get($key); };
$selectedDepts = array_filter(array_map('intval', explode(',', (string) $s['ticket_departments'])));
?>
<form method="post" action="<?= $e($link) ?>&amp;tab=settings" class="ntz-settings" data-settings-form autocomplete="off">
    <input type="hidden" name="_ntz" value="<?= $e($csrf) ?>">
    <input type="hidden" name="netarz_settings" value="1">

    <nav class="ntz-settings-nav" aria-label="<?= $t('tab_settings') ?>">
        <a href="#s-connection"><?= $icon('zap', 15) ?> <?= $t('s_connection') ?></a>
        <a href="#s-voice"><?= $icon('bot', 15) ?> <?= $t('s_voice') ?></a>
        <a href="#s-chat"><?= $icon('message-circle', 15) ?> <?= $t('s_chat') ?></a>
        <a href="#s-tickets"><?= $icon('ticket', 15) ?> <?= $t('s_tickets') ?></a>
        <a href="#s-knowledge"><?= $icon('book-open', 15) ?> <?= $t('s_knowledge') ?></a>
        <a href="#s-money"><?= $icon('wallet', 15) ?> <?= $t('s_money') ?></a>
    </nav>

    <div class="ntz-settings-body">
        <fieldset class="ntz-card" id="s-connection">
            <legend><?= $icon('zap', 18) ?> <?= $t('s_connection') ?></legend>
            <div class="ntz-field">
                <label class="ntz-label" for="ntz-api_key"><?= $t('f_api_key') ?></label>
                <div class="ntz-row is-tight">
                    <input class="ntz-input" type="password" id="ntz-api_key" name="api_key" value="<?= $e($maskedKey) ?>" placeholder="sk-ntz-v1-…" dir="ltr" autocomplete="new-password" spellcheck="false">
                    <button type="button" class="ntz-btn" data-action="test-connection"><?= $icon('shield-check', 15) ?> <?= $t('f_test') ?></button>
                </div>
                <small class="ntz-hint"><?= $t('f_api_key_hint') ?> <a href="https://netarz.ir/ai" target="_blank" rel="noopener">netarz.ir/ai</a></small>
                <div class="ntz-test-result" data-test-result hidden></div>
            </div>
            <div class="ntz-grid ntz-grid-2 is-flat">
                <div class="ntz-field">
                    <label class="ntz-label" for="ntz-model"><?= $t('f_model') ?></label>
                    <input class="ntz-input" type="text" id="ntz-model" name="model" value="<?= $e($s['model']) ?>" list="ntz-models" dir="ltr" spellcheck="false">
                    <datalist id="ntz-models"></datalist>
                    <small class="ntz-hint" data-model-price><?= $t('f_model_hint') ?></small>
                </div>
                <?= $field('base_url', 'f_base_url', 'f_base_url_hint', 'url', ['dir' => 'ltr']) ?>
                <?= $field('temperature', 'f_temperature', 'f_temperature_hint', 'number', ['min' => '0', 'max' => '1.5', 'step' => '0.1']) ?>
                <?= $field('max_tokens', 'f_max_tokens', 'f_max_tokens_hint', 'number', ['min' => '100', 'max' => '4000', 'step' => '50']) ?>
            </div>
        </fieldset>

        <fieldset class="ntz-card" id="s-voice">
            <legend><?= $icon('bot', 18) ?> <?= $t('s_voice') ?></legend>
            <div class="ntz-grid ntz-grid-2 is-flat">
                <?= $field('agent_name', 'f_agent_name', 'f_agent_name_hint', 'text', ['placeholder' => $L('default_agent_name')]) ?>
                <?= $field('brand_name', 'f_brand_name', 'f_brand_name_hint', 'text', ['placeholder' => \NetArz\WhmcsAi\Whmcs::companyName()]) ?>
                <?= $select('language', 'f_language', ['auto' => $L('lang_auto'), 'fa' => $L('lang_fa'), 'en' => $L('lang_en')], 'f_language_hint') ?>
                <?= $select('tone', 'f_tone', ['friendly' => $L('tone_friendly'), 'formal' => $L('tone_formal')]) ?>
            </div>
            <?= $textarea('instructions', 'f_instructions', 'f_instructions_hint', 6, 'f_instructions_placeholder') ?>
        </fieldset>

        <fieldset class="ntz-card" id="s-chat">
            <legend><?= $icon('message-circle', 18) ?> <?= $t('s_chat') ?></legend>
            <div class="ntz-switches">
                <?= $switch('chat_enabled', 'f_chat_enabled', 'f_chat_enabled_hint') ?>
                <?= $switch('chat_ai_enabled', 'f_chat_ai', 'f_chat_ai_hint') ?>
                <?= $switch('chat_guests', 'f_chat_guests', 'f_chat_guests_hint') ?>
                <?= $switch('chat_guest_email', 'f_chat_guest_email', 'f_chat_guest_email_hint') ?>
                <?= $switch('chat_admin_alerts', 'f_chat_alerts', 'f_chat_alerts_hint') ?>
                <?= $switch('chat_credit', 'f_chat_credit', 'f_chat_credit_hint') ?>
            </div>
            <div class="ntz-grid ntz-grid-3 is-flat">
                <?= $select('chat_position', 'f_chat_position', ['right' => $L('pos_right'), 'left' => $L('pos_left')]) ?>
                <?= $field('chat_color', 'f_chat_color', '', 'color') ?>
                <?= $field('chat_text_color', 'f_chat_text_color', '', 'color') ?>
                <?= $field('chat_burst_ms', 'f_chat_burst', 'f_chat_burst_hint', 'number', ['min' => '0', 'max' => '10000', 'step' => '250']) ?>
                <?= $field('chat_handoff_wait', 'f_chat_wait', 'f_chat_wait_hint', 'number', ['min' => '1', 'max' => '240']) ?>
                <?= $field('chat_max_per_hour', 'f_chat_rate', 'f_chat_rate_hint', 'number', ['min' => '1', 'max' => '1000']) ?>
                <?= $field('chat_max_length', 'f_chat_len', '', 'number', ['min' => '100', 'max' => '8000']) ?>
                <?= $field('chat_history_turns', 'f_chat_history', 'f_chat_history_hint', 'number', ['min' => '2', 'max' => '60']) ?>
            </div>
            <?= $textarea('chat_greeting', 'f_chat_greeting', 'f_chat_greeting_hint', 2) ?>
            <?= $textarea('chat_hide_paths', 'f_chat_hide', 'f_chat_hide_hint', 3) ?>
        </fieldset>

        <fieldset class="ntz-card" id="s-tickets">
            <legend><?= $icon('ticket', 18) ?> <?= $t('s_tickets') ?></legend>
            <div class="ntz-modes">
                <?php foreach (['off', 'draft', 'auto'] as $mode): ?>
                    <label class="ntz-mode">
                        <input type="radio" name="ticket_mode" value="<?= $mode ?>"<?= $s['ticket_mode'] === $mode ? ' checked' : '' ?>>
                        <span><strong><?= $t('mode_'.$mode) ?></strong><small><?= $t('mode_'.$mode.'_hint') ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="ntz-grid ntz-grid-2 is-flat">
                <?php $adminOptions = ['' => '—'] + $admins; ?>
                <?= $select('ticket_admin', 'f_ticket_admin', $adminOptions, 'f_ticket_admin_hint') ?>
                <?= $select('ticket_status_after', 'f_ticket_status', ['' => $L('status_default'), 'Answered' => 'Answered', 'In Progress' => 'In Progress', 'On Hold' => 'On Hold', 'Open' => 'Open']) ?>
                <?= $field('ticket_min_confidence', 'f_ticket_conf', 'f_ticket_conf_hint', 'number', ['min' => '0', 'max' => '100']) ?>
                <?= $field('ticket_max_auto', 'f_ticket_max', 'f_ticket_max_hint', 'number', ['min' => '1', 'max' => '50']) ?>
                <?= $field('ticket_delay', 'f_ticket_delay', 'f_ticket_delay_hint', 'number', ['min' => '0', 'max' => '1440']) ?>
            </div>
            <div class="ntz-field">
                <span class="ntz-label"><?= $t('f_ticket_depts') ?></span>
                <input type="hidden" name="ticket_departments" value="">
                <div class="ntz-chips">
                    <?php foreach ($departments as $id => $name): ?>
                        <label class="ntz-chip"><input type="checkbox" name="ticket_departments[]" value="<?= (int) $id ?>"<?= in_array((int) $id, $selectedDepts, true) ? ' checked' : '' ?>><span><?= $e($name) ?></span></label>
                    <?php endforeach; ?>
                    <?php if (! $departments): ?><span class="ntz-muted"><?= $t('no_departments') ?></span><?php endif; ?>
                </div>
                <small class="ntz-hint"><?= $t('f_ticket_depts_hint') ?></small>
            </div>
            <div class="ntz-switches">
                <?= $switch('ticket_instant', 'f_ticket_instant', 'f_ticket_instant_hint') ?>
                <?= $switch('ticket_note_handoff', 'f_ticket_note', 'f_ticket_note_hint') ?>
                <?= $switch('ticket_skip_human', 'f_ticket_skip_human', 'f_ticket_skip_human_hint') ?>
            </div>
            <?= $textarea('ticket_signature', 'f_ticket_signature', 'f_ticket_signature_hint', 2) ?>
        </fieldset>

        <fieldset class="ntz-card" id="s-knowledge">
            <legend><?= $icon('book-open', 18) ?> <?= $t('s_knowledge') ?></legend>
            <div class="ntz-switches">
                <?= $switch('knowledge_kb', 'f_kn_kb') ?>
                <?= $switch('knowledge_announcements', 'f_kn_ann') ?>
                <?= $switch('knowledge_products', 'f_kn_products') ?>
                <?= $switch('knowledge_domains', 'f_kn_domains') ?>
                <?= $switch('knowledge_network', 'f_kn_network') ?>
                <?= $switch('knowledge_client', 'f_kn_client', 'f_kn_client_hint') ?>
            </div>
            <p class="ntz-muted ntz-small"><?= $t('f_kn_custom_where') ?> <a href="<?= $e($link) ?>&amp;tab=knowledge"><?= $t('tab_knowledge') ?></a></p>
        </fieldset>

        <fieldset class="ntz-card" id="s-money">
            <legend><?= $icon('wallet', 18) ?> <?= $t('s_money') ?></legend>
            <div class="ntz-grid ntz-grid-3 is-flat">
                <?= $field('daily_budget', 'f_budget', 'f_budget_hint', 'number', ['min' => '0', 'step' => '0.5']) ?>
                <?= $field('min_balance', 'f_min_balance', 'f_min_balance_hint', 'number', ['min' => '0', 'step' => '0.1']) ?>
                <?= $field('retention_days', 'f_retention', 'f_retention_hint', 'number', ['min' => '7', 'max' => '3650']) ?>
            </div>
            <div class="ntz-switches"><?= $switch('balance_alerts', 'f_alerts', 'f_alerts_hint') ?></div>
        </fieldset>

        <div class="ntz-savebar">
            <button type="submit" class="ntz-btn is-primary"><?= $icon('check', 16) ?> <?= $t('save_settings') ?></button>
        </div>
    </div>
</form>

<details class="ntz-card ntz-danger">
    <summary><?= $icon('triangle-alert', 16) ?> <?= $t('danger_title') ?></summary>
    <form method="post" action="<?= $e($link) ?>&amp;tab=settings" class="ntz-row">
        <input type="hidden" name="_ntz" value="<?= $e($csrf) ?>">
        <input type="hidden" name="netarz_purge" value="1">
        <p class="ntz-muted"><?= $t('danger_text') ?></p>
        <input class="ntz-input is-sm" name="confirm" placeholder="DELETE" dir="ltr" required pattern="DELETE">
        <button type="submit" class="ntz-btn is-danger"><?= $icon('trash-2', 15) ?> <?= $t('danger_button') ?></button>
    </form>
</details>
