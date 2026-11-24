<?php
$count = $total === null ? t('Loading…') : t('%d of %d subtasks', $done, $total);
$time = t('%sh / %sh', $spentH, $estH);
if ($state === 'over') {
    $time .= ' · ' . t('over budget');
}
?>
<div data-slot="progress" data-state="<?= $this->text->e($state) ?>" style="--pct:<?= (int) $pct ?>%">
    <div data-slot="progress-track" role="progressbar" aria-label="<?= $this->text->e(t('Subtask progress')) ?>"
        <?php if ($total !== null): ?>
            aria-valuenow="<?= (int) $pct ?>" aria-valuetext="<?= $this->text->e($count . ' · ' . $time) ?>"
        <?php endif ?>
    ><span></span></div>
    <div data-slot="progress-labels" aria-hidden="true">
        <span><?= $this->text->e($count) ?></span>
        <?php if ($total !== null): ?>
            <span><?= $this->text->e($time) ?></span>
        <?php endif ?>
    </div>
</div>
