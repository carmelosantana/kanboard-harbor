<div data-slot="usage-bar" data-level="<?= $level ?>" style="--pct:<?= $pct ?>%">
    <div data-usage-labels>
        <span><?= $this->text->e($label) ?></span>
        <strong><?= $this->text->e(trim("$used / $total $unit")) ?></strong>
    </div>
    <div data-usage-track aria-hidden="true"><span></span></div>
</div>
