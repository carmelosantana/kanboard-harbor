<div data-slot="heatmap" role="group" aria-label="<?= $this->text->e(t('Activity heatmap')) ?>">
    <?php foreach ($days as $day => $count): ?>
        <?php $label = $this->text->e(t('%d events on %s', $count, $day)); ?>
        <span role="img" style="--level:<?= $levels[$day] ?>" aria-label="<?= $label ?>" title="<?= $label ?>"></span>
    <?php endforeach ?>
</div>
