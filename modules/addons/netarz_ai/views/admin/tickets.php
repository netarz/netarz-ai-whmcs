<?php
use NetArz\WhmcsAi\Settings;

$filters = ['' => 'filter_all', 'replied' => 'outcome_replied', 'drafted' => 'outcome_drafted', 'handoff' => 'outcome_handoff', 'low_balance' => 'outcome_low_balance', 'budget' => 'outcome_budget'];
$current = isset($_GET['outcome']) ? (string) $_GET['outcome'] : '';
?>
<section class="ntz-card">
    <div class="ntz-card-head">
        <span class="ntz-card-icon"><?= $icon('ticket', 18) ?></span>
        <span><?= $t('tickets_title') ?></span>
        <span class="ntz-pill is-<?= $e(Settings::get('ticket_mode')) ?>"><?= $t('mode_'.Settings::get('ticket_mode')) ?></span>
    </div>
    <p class="ntz-muted"><?= $t('tickets_intro') ?></p>
    <div class="ntz-seg is-links">
        <?php foreach ($filters as $key => $label): ?>
            <a class="<?= $current === $key ? 'is-active' : '' ?>" href="<?= $e($link) ?>&amp;tab=tickets<?= $key !== '' ? '&amp;outcome='.$e($key) : '' ?>"><?= $t($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (! $jobs): ?>
        <p class="ntz-empty"><?= $t('tickets_empty') ?></p>
    <?php else: ?>
        <div class="ntz-table-wrap">
            <table class="ntz-table">
                <thead><tr>
                    <th><?= $t('col_ticket') ?></th><th><?= $t('col_event') ?></th><th><?= $t('col_outcome') ?></th>
                    <th><?= $t('confidence') ?></th><th><?= $t('col_reason') ?></th><th><?= $t('col_time') ?></th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td><a href="supporttickets.php?action=view&amp;id=<?= (int) $job->ticket_id ?>">#<?= (int) $job->ticket_id ?></a></td>
                        <td><?= $t('event_'.$job->event) ?></td>
                        <td><span class="ntz-pill is-<?= $e($job->outcome ?: $job->status) ?>"><?= $t('outcome_'.($job->outcome ?: $job->status)) ?></span></td>
                        <td><?= (int) $job->confidence > 0 ? (int) $job->confidence.'%' : '—' ?></td>
                        <td class="ntz-clip" dir="auto" title="<?= $e($job->error ?: $job->reason) ?>"><?= $e($job->reason !== '' ? \NetArz\WhmcsAi\Lang::reason($job->reason) : ($job->error ?? '')) ?></td>
                        <td class="ntz-nowrap"><?= $e(substr((string) $job->updated_at, 0, 16)) ?></td>
                        <td class="ntz-nowrap">
                            <?php if ((string) $job->draft !== ''): ?>
                                <details class="ntz-draft-peek"><summary><?= $t('view_draft') ?></summary><div dir="auto"><?= nl2br($e($job->draft)) ?></div></details>
                            <?php endif; ?>
                            <?php if (in_array($job->status, ['failed', 'skipped'], true)): ?>
                                <button type="button" class="ntz-btn is-sm" data-action="retry-job" data-id="<?= (int) $job->id ?>"><?= $icon('refresh-cw', 14) ?> <?= $t('retry') ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
