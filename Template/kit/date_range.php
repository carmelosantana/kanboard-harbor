<div data-slot="date-range" data-from="<?= $this->text->e($from ?? '') ?>" data-to="<?= $this->text->e($to ?? '') ?>">
    <div data-slot="segmented" role="group" aria-label="<?= $this->text->e(t('Time range')) ?>">
        <?php foreach ($presets as $label => $seconds): ?>
            <button type="button" data-range-preset="<?= $seconds ?>" aria-pressed="false"><?= $this->text->e($label) ?></button>
        <?php endforeach ?>
        <button type="button" data-range-custom aria-pressed="false" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?= $id ?>"><?= t('Custom…') ?></button>
    </div>
    <div id="<?= $id ?>" data-range-panel aria-label="<?= $this->text->e(t('Choose a date range')) ?>" hidden>
        <div class="kit-range-actions">
            <button type="button" data-range-previous aria-label="<?= $this->text->e(t('Previous month')) ?>">‹</button>
            <button type="button" data-range-next aria-label="<?= $this->text->e(t('Next month')) ?>">›</button>
        </div>
        <div data-range-months></div>
        <div class="kit-range-actions">
            <span data-range-summary aria-live="polite"></span>
            <button type="button" data-range-cancel><?= t('Cancel') ?></button>
            <button type="button" data-range-apply disabled><?= t('Apply') ?></button>
        </div>
    </div>
</div>
