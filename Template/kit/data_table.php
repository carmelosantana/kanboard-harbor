<div data-slot="data-table">
    <?php if (isset($pager['toolbar'])): ?>
        <?= $this->kit->toolbar($pager['toolbar']) ?>
    <?php endif ?>
    <div class="table-wrap" tabindex="0" role="region" aria-label="<?= $this->text->e(t('Scrollable data table')) ?>">
        <table>
            <thead>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <th scope="col"<?= $column['sort'] === null ? '' : ' aria-sort="' . $column['sort'] . '"' ?>>
                            <?php if ($column['sortUrl'] !== null): ?>
                                <a href="<?= $this->text->e($column['sortUrl']) ?>"><?= $this->text->e($column['label']) ?></a>
                            <?php else: ?>
                                <?= $this->text->e($column['label']) ?>
                            <?php endif ?>
                        </th>
                    <?php endforeach ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($columns as $key => $column): ?>
                            <td><?= $this->text->e($row[$key] ?? '') ?></td>
                        <?php endforeach ?>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <nav class="kit-table-pager" aria-label="<?= $this->text->e(t('Pagination')) ?>">
        <span><?= $this->text->e($range) ?></span>
        <div>
            <?php if ($previous !== null): ?>
                <a href="<?= $this->text->e($previous) ?>" rel="prev"><?= t('Previous') ?></a>
            <?php endif ?>
            <?php if ($next !== null): ?>
                <a href="<?= $this->text->e($next) ?>" rel="next"><?= t('Next') ?></a>
            <?php endif ?>
        </div>
    </nav>
</div>
