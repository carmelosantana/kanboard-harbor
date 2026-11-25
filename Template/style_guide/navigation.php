<section>
    <h3><?= t('Breadcrumbs') ?></h3>
    <?= $this->kit->breadcrumbs([
        ['label' => t('Settings'), 'href' => ['ConfigController', 'index']],
        ['label' => 'Harbor'],
        ['label' => t('Design system')],
        ['label' => t('Style guide')],
    ]) ?>
</section>

<section>
    <h3><?= t('Date range') ?></h3>
    <?= $this->kit->dateRange($from, $to) ?>
</section>
