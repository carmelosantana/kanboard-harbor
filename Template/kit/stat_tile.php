<div data-slot="stat-tile">
    <span><?= $this->text->e($label) ?></span>
    <strong><?= $this->text->e($value) ?></strong>
    <?php if ($points !== null): ?>
        <span data-slot="sparkline" data-points="<?= $this->text->e($points) ?>" aria-hidden="true"></span>
    <?php endif ?>
</div>
