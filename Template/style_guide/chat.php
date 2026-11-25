<section id="style-guide-chat">
    <h3><?= t('Chat thread and composer') ?></h3>
    <?php ob_start(); ?>
    <div data-slot="float" data-kind="pill">#design · <?= t('Suite design system') ?></div>
    <div data-slot="chat-thread" tabindex="0" aria-label="<?= $this->text->e(t('Design chat')) ?>">
        <?= $this->kit->chatMessage('Carmelo', t('Which plugins still hard-code status colours?'), '10:40', true) ?>
        <?= $this->kit->chatMessage('Design reviewer', t('Seven plugins still use private status colours. I’m drafting the switch to kit pills.'), '10:41', false, [
            t('Scan Assets/css · 30 plugins'),
        ]) ?>
        <?= $this->kit->chatMessage('Marisol', t('Keep LanParty’s arcade palette for the game artwork.'), '10:43', false) ?>
    </div>
    <?= $this->kit->composer(t('Message #design…'), $action) ?>
    <?= $this->kit->stage(ob_get_clean()) ?>
    <p><?= t('Sample messages are discarded when submitted.') ?></p>
</section>
