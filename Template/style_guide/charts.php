<section>
    <h3><?= t('Stat tiles and sparklines') ?></h3>
    <?= $this->kit->statTile(t('CPU'), '37%', [24, 28, 21, 33, 31, 42, 37]) ?>
    <?= $this->kit->statTile(t('Uptime'), '18d 4h') ?>
</section>

<section>
    <h3><?= t('CPU usage over the past 15 minutes (%%)') ?></h3>
    <?= $this->kit->lineChart([
        ['name' => t('User'), 'values' => [23, 28, 24, 39, 27, 29]],
        ['name' => t('System'), 'values' => [8, 7, 12, 15, 11, 6]],
        ['name' => t('I/O wait'), 'values' => [2, 3, 5, 4, 3, 2]],
    ], ['14:17', '14:20', '14:23', '14:26', '14:29', '14:32']) ?>
</section>

<section>
    <h3><?= t('Storage capacity') ?></h3>
    <?= $this->kit->usageBar('tank', 3.36, 4, 'TB') ?>
    <?= $this->kit->usageBar('tank/vm', 1.86, 2, 'TB') ?>
    <?= $this->kit->usageBar('tank/apps', 460, 1000, 'GB') ?>
</section>
