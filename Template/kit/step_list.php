<ol data-slot="step-list">
    <?php foreach ($steps as $step): ?>
        <li data-slot="step" data-state="<?= $this->text->e($step['state']) ?>">
            <span class="kit-step-icon" role="img" aria-label="<?= $this->text->e($step['stateLabel']) ?>"><?= $step['icon'] ?></span>
            <div>
                <strong><?= $this->text->e($step['label']) ?></strong>
                <?php if ($step['summary'] !== ''): ?>
                    <div class="kit-step-detail"><?= $this->text->e($step['summary']) ?></div>
                <?php endif ?>
            </div>
            <?php if ($step['state'] === 'failed'): ?>
                <span data-slot="retry">
                    <?php if (isset($step['retry'])): ?>
                        <?= $step['retry']() ?>
                    <?php else: ?>
                        <button type="button" disabled><?= t('Retry') ?></button>
                    <?php endif ?>
                </span>
            <?php endif ?>
        </li>
    <?php endforeach ?>
</ol>
