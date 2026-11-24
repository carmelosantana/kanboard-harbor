<div data-slot="skeleton" data-kind="<?= $this->text->e($kind) ?>" role="status" aria-busy="true" aria-label="<?= $this->text->e(t('Loading')) ?>">
    <?php if ($kind === 'row'): ?>
        <span data-slot="skeleton-piece" data-shape="circle" style="--piece-width:1.75rem;--piece-height:1.75rem"></span>
        <span data-slot="skeleton-lines">
            <span data-slot="skeleton-piece" style="--piece-width:80%"></span>
            <span data-slot="skeleton-piece" style="--piece-width:40%"></span>
        </span>
        <span data-slot="skeleton-piece"></span>
    <?php elseif ($kind === 'tile'): ?>
        <span data-slot="skeleton-piece" style="--piece-width:40%"></span>
        <span data-slot="skeleton-piece" style="--piece-width:60%;--piece-height:1.625rem"></span>
        <span data-slot="skeleton-piece" style="--piece-height:.375rem"></span>
    <?php else: ?>
        <span data-slot="skeleton-piece" style="--piece-width:80%"></span>
        <span data-slot="skeleton-piece" style="--piece-height:.375rem"></span>
        <span data-slot="skeleton-piece" style="--piece-width:40%"></span>
    <?php endif ?>
</div>
