<section>
    <h3><?= t('Status badges') ?></h3>
    <?php foreach (['running', 'stopped', 'starting', 'failed', 'degraded', 'pending'] as $state): ?>
        <?= $this->kit->status($state) ?>
    <?php endforeach ?>
</section>

<section>
    <h3><?= t('Audit table') ?></h3>
    <?= $this->kit->dataTable(
        ['time' => ['label' => t('Time'), 'sort' => 'descending'], 'actor' => t('Actor'),
            'verb' => t('Verb'), 'target' => t('Target'), 'outcome' => t('Outcome')],
        [
            ['time' => '14:32:08', 'actor' => 'carmelo', 'verb' => 'snapshot.create', 'target' => 'tank/apps/kanboard', 'outcome' => t('Allowed')],
            ['time' => '14:29:51', 'actor' => 'foreman', 'verb' => 'apps.restart', 'target' => 'valkey', 'outcome' => t('Allowed')],
            ['time' => '14:12:40', 'actor' => 'agent:kb-ops', 'verb' => 'vms.destroy', 'target' => 'win11-test', 'outcome' => t('Denied')],
        ],
        ['start' => 1, 'end' => 3, 'total' => 3],
    ) ?>
</section>

<section>
    <h3><?= t('Live log viewer') ?></h3>
    <?= $this->kit->logViewer('backup-sqlite', $sampleLog) ?>
</section>

<section>
    <h3><?= t('Job steps') ?></h3>
    <?= $this->kit->stepList([
        ['label' => t('Snapshot tank/apps/kanboard'), 'state' => 'done', 'duration' => '0.4 s'],
        ['label' => t('Dump SQLite'), 'state' => 'done', 'duration' => '2.1 s', 'detail' => '48 MB'],
        ['label' => t('Compress and upload'), 'state' => 'active', 'detail' => t('63%% · 1 min left')],
        ['label' => t('Rotate old backups'), 'state' => 'waiting'],
        ['label' => t('Send notification'), 'state' => 'failed', 'detail' => t('Notification provider rejected the request')],
    ]) ?>
</section>

<section id="style-guide-approval">
    <h3><?= t('Approval countdown') ?></h3>
    <?= $this->kit->approvalCard(time() + 300, [
        t('Verb') => 'snapshot.rollback',
        t('Target') => 'tank/vm/win11@2026-11-23-0400',
        t('Requested by') => 'carmelo · 14:31',
    ], '', t('Approve sample request')) ?>
</section>

<section id="style-guide-confirm">
    <h3><?= t('Typed confirmation') ?></h3>
    <p><?= t('Try the confirmation for a sample VM. Submitting returns to this guide.') ?></p>
    <button type="button" class="btn" command="show-modal" commandfor="style-guide-confirm-dialog">
        <?= t('Preview confirmation') ?>
    </button>
    <?php
    // The guide uses native dialog commands as declarative triggers.
    // The module still binds its input and submit guards to the rendered form.
    echo strtr($this->kit->typedConfirm(
        t('Destroy VM win11-test?'),
        '<p>' . t('This would remove the VM and its disk dataset, including 14 snapshots.') . '</p>',
        'win11-test',
        t('Confirm sample'),
        $action,
    ), [
        'data-popup-preset="dialog"' => 'id="style-guide-confirm-dialog" class="float"',
        '" hidden>' => '">',
        'data-confirm-cancel>' => 'data-confirm-cancel command="close" commandfor="style-guide-confirm-dialog">',
    ]);
    ?>
</section>

<section>
    <h3><?= t('Service tiles') ?></h3>
    <?= $this->kit->serviceTile('Kanboard', 'running', t('Web application · uptime 18d 4h')) ?>
    <?= $this->kit->serviceTile('Valkey', 'degraded', t('Cache · memory pressure above 90%%')) ?>
    <?= $this->kit->serviceTile('win11-test', 'stopped', t('Virtual machine · 4 vCPU, 8 GB RAM')) ?>
</section>
