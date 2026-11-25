<dialog data-slot="shortcut-sheet" aria-label="<?= $this->text->e(e('Keyboard shortcuts')) ?>">
    <h2><?= t('Keyboard shortcuts') ?></h2>
    <?php foreach ($groups as $slot => $shortcuts): ?>
        <h3><?= $this->text->e($slot) ?></h3>
        <dl>
            <?php foreach ($shortcuts as $shortcut): ?>
                <dt><?= $this->text->e($shortcut['label']) ?></dt>
                <dd><?= $this->kit->kbd($shortcut['key']) ?></dd>
            <?php endforeach ?>
        </dl>
    <?php endforeach ?>
    <button type="button" class="btn" data-shortcut-close><?= t('Close') ?></button>
</dialog>
