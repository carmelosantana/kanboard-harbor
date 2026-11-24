<?php $searchLabel = $opts['searchLabel'] ?? t('Search'); ?>
<form data-slot="toolbar" role="search" method="get">
    <?php foreach ($opts['params'] ?? [] as $name => $value): ?>
        <input type="hidden" name="<?= $this->text->e($name) ?>" value="<?= $this->text->e($value) ?>">
    <?php endforeach ?>
    <input data-slot="toolbar-search" type="search" name="<?= $this->text->e($opts['searchName'] ?? 'search') ?>"
           value="<?= $this->text->e($opts['query'] ?? '') ?>" placeholder="<?= $this->text->e($searchLabel) ?>"
           aria-label="<?= $this->text->e($searchLabel) ?>">
    <?php foreach ($opts['selects'] ?? [] as $select): ?>
        <select data-slot="control" name="<?= $this->text->e($select['name']) ?>" aria-label="<?= $this->text->e($select['label']) ?>">
            <?php foreach ($select['options'] as $value => $label): ?>
                <option value="<?= $this->text->e($value) ?>"<?= (string) $value === (string) $select['value'] ? ' selected' : '' ?>><?= $this->text->e($label) ?></option>
            <?php endforeach ?>
        </select>
    <?php endforeach ?>
    <div data-slot="toolbar-actions">
        <button type="submit" class="btn btn-blue"><?= $this->text->e($opts['submitLabel'] ?? t('Search')) ?></button>
    </div>
    <?php if (!empty($opts['chips']) || isset($opts['resultCount'])): ?>
        <div data-slot="toolbar-filters">
            <?php foreach ($opts['chips'] ?? [] as $chip): ?>
                <label data-slot="chip">
                    <input type="checkbox" name="<?= $this->text->e($chip['name']) ?>" value="<?= $this->text->e($chip['value']) ?>"<?= !empty($chip['checked']) ? ' checked' : '' ?>>
                    <?= $this->text->e($chip['label']) ?>
                </label>
            <?php endforeach ?>
            <?php if (isset($opts['resultCount'])): ?>
                <span data-slot="result-count" role="status"><?= $this->text->e($opts['resultCount']) ?></span>
            <?php endif ?>
        </div>
    <?php endif ?>
</form>
