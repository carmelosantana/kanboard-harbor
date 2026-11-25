<section>
    <h3><?= t('Avatars and presence') ?></h3>
    <?= $this->kit->avatar('Carmelo Santana', 'online') ?>
    <?= $this->kit->avatar('Marisol Stone', 'away') ?>
    <?= $this->kit->avatar('Design reviewer', 'offline') ?>
</section>

<section>
    <h3><?= t('Copy button') ?></h3>
    <code>tank/apps/kanboard</code> <?= $this->kit->copyButton('tank/apps/kanboard') ?>
</section>

<section>
    <h3><?= t('Keyboard hints and shortcut sheet') ?></h3>
    <p><?= e('Press %s to open the shortcut sheet.', $this->kit->kbd('?')) ?></p>
    <?= $this->kit->shortcutSheet() ?>
</section>

<section>
    <h3><?= t('Visually hidden text') ?></h3>
    <p><?= t('This icon has a text label for screen readers.') ?></p>
    <span aria-hidden="true">✓</span><?= $this->kit->visuallyHidden(t('Backup complete')) ?>
</section>
