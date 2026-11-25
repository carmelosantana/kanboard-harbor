<div data-chart-frame>
    <?php if (count($series) > 1): ?>
        <ul data-slot="chart-legend">
            <?php foreach ($series as $entry): ?>
                <li data-chart-color="<?= $entry['color'] ?>"><?= $this->text->e($entry['name']) ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
    <div data-slot="line-chart" data-series="<?= $this->text->e($seriesJson) ?>" data-labels="<?= $this->text->e($labelsJson) ?>"
        data-chart-description="<?= $this->text->e(t('Line chart. Use the left and right arrow keys to explore the data.')) ?>"></div>
    <details>
        <summary><?= t('Chart data') ?></summary>
        <div class="table-wrap">
            <table data-slot="chart-table">
                <thead>
                    <tr>
                        <th scope="col"><?= t('Label') ?></th>
                        <?php foreach ($series as $entry): ?>
                            <th scope="col"><?= $this->text->e($entry['name']) ?></th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($labels as $index => $label): ?>
                        <tr>
                            <th scope="row"><?= $this->text->e($label) ?></th>
                            <?php foreach ($series as $entry): ?>
                                <td><?= $this->text->e($entry['values'][$index] ?? '—') ?></td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </details>
</div>
