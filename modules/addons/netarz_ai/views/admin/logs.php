<section class="ntz-card">
    <div class="ntz-card-head"><span class="ntz-card-icon"><?= $icon('clock', 18) ?></span><span><?= $t('logs_title') ?></span></div>
    <p class="ntz-muted"><?= $t('logs_intro') ?></p>
    <?php if (! $logs): ?>
        <p class="ntz-empty"><?= $t('logs_empty') ?></p>
    <?php else: ?>
        <div class="ntz-table-wrap">
            <table class="ntz-table is-compact">
                <thead><tr>
                    <th><?= $t('col_time') ?></th><th><?= $t('col_channel') ?></th><th><?= $t('col_ref') ?></th><th><?= $t('f_model') ?></th>
                    <th><?= $t('col_tokens') ?></th><th><?= $t('col_cost') ?></th><th><?= $t('col_latency') ?></th><th><?= $t('col_result') ?></th><th>ID</th>
                </tr></thead>
                <tbody>
                <?php foreach ($logs as $row): ?>
                    <tr class="<?= $row->status === 'error' ? 'is-error' : '' ?>">
                        <td class="ntz-nowrap"><?= $e(substr((string) $row->created_at, 0, 16)) ?></td>
                        <td><?= $t('channel_'.$row->channel) ?></td>
                        <td><?php if ($row->channel === 'ticket' && $row->ref_id): ?><a href="supporttickets.php?action=view&amp;id=<?= (int) $row->ref_id ?>">#<?= (int) $row->ref_id ?></a><?php elseif ($row->ref_id): ?>#<?= (int) $row->ref_id ?><?php endif; ?></td>
                        <td dir="ltr" class="ntz-clip"><?= $e($row->model) ?></td>
                        <td dir="ltr"><?= (int) $row->input_tokens ?> / <?= (int) $row->output_tokens ?></td>
                        <td dir="ltr">$<?= number_format((float) $row->cost_usd, 6) ?></td>
                        <td dir="ltr"><?= number_format((int) $row->latency_ms / 1000, 1) ?>s</td>
                        <td>
                            <?php if ($row->status === 'error'): ?><span class="ntz-pill is-failed" title="<?= $e($row->error) ?>"><?= $e($row->error) ?></span>
                            <?php else: ?><span class="ntz-pill is-<?= $e($row->action) ?>"><?= $e($row->action) ?></span> <span class="ntz-muted"><?= (int) $row->confidence ?>%</span><?php endif; ?>
                        </td>
                        <td dir="ltr" class="ntz-muted ntz-clip"><?= $e($row->request_id) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
