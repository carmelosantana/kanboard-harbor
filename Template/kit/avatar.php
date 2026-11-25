<span data-slot="avatar">
    <span role="img" aria-label="<?= $this->text->e($name) ?>"><?= $this->text->e($initials) ?></span>
    <?php if ($presence !== null): ?>
        <span data-presence="<?= $this->text->e($presence) ?>" role="img" aria-label="<?= $this->text->e($presenceLabel) ?>"></span>
    <?php endif ?>
</span>
