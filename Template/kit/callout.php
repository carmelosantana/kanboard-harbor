<div data-slot="callout" data-tone="<?= $this->text->e($tone) ?>" role="<?= $tone === 'error' ? 'alert' : 'status' ?>">
    <strong><?= $this->text->e($title) ?></strong>
    <span><?= $this->text->e($body) ?></span>
</div>
