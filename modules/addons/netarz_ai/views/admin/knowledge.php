<?php use NetArz\WhmcsAi\Settings; ?>
<section class="ntz-grid ntz-grid-2">
    <article class="ntz-card">
        <div class="ntz-card-head"><span class="ntz-card-icon"><?= $icon('book-open', 18) ?></span><span><?= $t('kn_title') ?></span></div>
        <p class="ntz-muted"><?= $t('kn_intro') ?></p>
        <ul class="ntz-sources">
            <li class="<?= Settings::bool('knowledge_kb') ? 'is-on' : '' ?>"><?= $icon(Settings::bool('knowledge_kb') ? 'check' : 'x', 15) ?> <?= $t('kn_src_kb', ['n' => (int) $kbCount]) ?></li>
            <li class="<?= Settings::bool('knowledge_announcements') ? 'is-on' : '' ?>"><?= $icon(Settings::bool('knowledge_announcements') ? 'check' : 'x', 15) ?> <?= $t('kn_src_ann', ['n' => (int) $annCount]) ?></li>
            <li class="<?= Settings::bool('knowledge_products') ? 'is-on' : '' ?>"><?= $icon(Settings::bool('knowledge_products') ? 'check' : 'x', 15) ?> <?= $t('kn_src_products') ?></li>
            <li class="<?= Settings::bool('knowledge_domains') ? 'is-on' : '' ?>"><?= $icon(Settings::bool('knowledge_domains') ? 'check' : 'x', 15) ?> <?= $t('kn_src_domains') ?></li>
            <li class="<?= Settings::bool('knowledge_network') ? 'is-on' : '' ?>"><?= $icon(Settings::bool('knowledge_network') ? 'check' : 'x', 15) ?> <?= $t('kn_src_network') ?></li>
            <li class="<?= Settings::bool('knowledge_client') ? 'is-on' : '' ?>"><?= $icon(Settings::bool('knowledge_client') ? 'check' : 'x', 15) ?> <?= $t('kn_src_client') ?></li>
        </ul>
        <form data-knowledge-form>
            <label class="ntz-label" for="ntz-kc"><?= $t('kn_custom') ?></label>
            <p class="ntz-muted ntz-small"><?= $t('kn_custom_hint') ?></p>
            <textarea id="ntz-kc" name="knowledge_custom" rows="14" class="ntz-input" maxlength="60000" placeholder="<?= $t('kn_custom_placeholder') ?>"><?= $e($custom) ?></textarea>
            <div class="ntz-row"><span class="ntz-muted ntz-small" data-kc-count></span>
                <button type="submit" class="ntz-btn is-primary"><?= $icon('check', 15) ?> <?= $t('save') ?></button></div>
        </form>
    </article>

    <article class="ntz-card" data-console>
        <div class="ntz-card-head"><span class="ntz-card-icon"><?= $icon('wand-sparkles', 18) ?></span><span><?= $t('kn_console') ?></span></div>
        <p class="ntz-muted"><?= $t('kn_console_intro') ?></p>
        <form data-console-form>
            <textarea name="question" rows="3" class="ntz-input" placeholder="<?= $t('kn_console_placeholder') ?>" required></textarea>
            <div class="ntz-row">
                <label class="ntz-inline"><?= $t('kn_console_as') ?>
                    <select name="channel" class="ntz-input is-sm"><option value="chat"><?= $t('kn_channel_chat') ?></option><option value="ticket"><?= $t('kn_channel_ticket') ?></option></select>
                </label>
                <label class="ntz-inline"><?= $t('kn_console_client') ?>
                    <input type="number" name="client_id" min="0" class="ntz-input is-sm" placeholder="0" style="width:90px">
                </label>
                <button type="button" class="ntz-btn is-sm" data-action="preview-knowledge"><?= $icon('book-open', 15) ?> <?= $t('kn_preview') ?></button>
                <button type="submit" class="ntz-btn is-primary is-sm"><?= $icon('play', 15) ?> <?= $t('kn_ask') ?></button>
            </div>
        </form>
        <div class="ntz-console-out" data-console-out hidden></div>
        <pre class="ntz-pre" data-knowledge-out hidden></pre>
    </article>
</section>
