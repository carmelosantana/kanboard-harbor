<div data-slot="empty">
    <h3><?= $this->text->e($title) ?></h3>
    <p><?= $this->text->e($body) ?></p>
    <?php if ($actionHtml !== ''): ?>
        <div data-slot="empty-action"><?= $actionHtml instanceof \Closure ? $actionHtml() : $this->text->e($actionHtml) ?></div>
    <?php endif ?>
</div>
