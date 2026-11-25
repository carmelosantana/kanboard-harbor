<nav data-slot="breadcrumbs" aria-label="<?= $this->text->e(t('Breadcrumb')) ?>">
    <ol tabindex="-1">
        <?php foreach ($items as $index => $item): ?>
            <?php if ($index === 1 && $last > 1): ?>
                <li hidden><button type="button" data-breadcrumb-toggle aria-label="<?= $this->text->e(t('Show hidden levels')) ?>" aria-expanded="false">…</button></li>
            <?php endif ?>
            <li>
                <?php if ($index === $last): ?>
                    <span aria-current="page"><?= $this->text->e($item['label']) ?></span>
                <?php elseif ($item['href'] !== null): ?>
                    <a href="<?= $this->text->e($item['href']) ?>"><?= $this->text->e($item['label']) ?></a>
                <?php else: ?>
                    <span><?= $this->text->e($item['label']) ?></span>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ol>
</nav>
