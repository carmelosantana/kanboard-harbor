<div class="page-header">
    <h2><?= t('Harbor style guide') ?></h2>
</div>

<section>
    <h3><?= t('Toolbar and pills') ?></h3>
    <?= $this->kit->toolbar([
        'query' => $query,
        'searchLabel' => t('Search example statuses'),
        'params' => ['controller' => 'HarborStyleGuideController', 'action' => 'show', 'plugin' => 'Harbor'],
        'resultCount' => t('%d of %d statuses', count($matches), $count),
    ]) ?>
    <?php foreach ($matches as $tone => $label): ?>
        <?= $this->kit->pill($label, $tone) ?>
    <?php endforeach ?>
</section>

<section>
    <h3><?= t('Feedback') ?></h3>
    <?= $this->kit->callout(t('Import paused'), t('Reconnect your account to continue.'), 'warning') ?>
</section>

<section>
    <h3><?= t('Progress') ?></h3>
    <?= $this->kit->progress(3, 5, 4.5, 8) ?>
    <?= $this->kit->progress(4, 6, 9.2, 6) ?>
    <?= $this->kit->progress(7, 7, 3, 4) ?>
    <?= $this->kit->progress(0, null, 0, 0) ?>
</section>

<section>
    <h3><?= t('Skeletons') ?></h3>
    <?= $this->kit->skeleton('row') ?>
    <?= $this->kit->skeleton('card') ?>
    <?= $this->kit->skeleton('tile') ?>
</section>

<section>
    <h3><?= t('Empty state') ?></h3>
    <?= $this->kit->empty(t('No statuses match'), t('Try another word or clear the search.'), fn () => $this->url->link(
        t('Clear search'), 'HarborStyleGuideController', 'show', ['plugin' => 'Harbor'], false, 'btn'
    )) ?>
</section>
