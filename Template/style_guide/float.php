<section>
    <h3><?= t('Floating stage') ?></h3>
    <?php ob_start(); ?>
    <p><?= t('The Long Harbor · S1 E3 · Low Tide') ?></p>
    <p><?= t('18:04 elapsed · 29:31 remaining') ?></p>
    <?= $this->kit->stage(ob_get_clean()) ?>
</section>

<section>
    <h3><?= t('Floating cards and controls') ?></h3>
    <?php ob_start(); ?>
    <div data-slot="float" data-kind="pill" data-float-controls><?= t('Now playing · Low Tide') ?></div>
    <div data-slot="float">
        <strong><?= t('Up next') ?></strong>
        <p><?= t('E4 · River Road') ?></p>
        <button type="button" data-slot="icon-button" aria-label="<?= $this->text->e(t('Subtitles')) ?>" disabled>CC</button>
    </div>
    <a href="#style-guide-chat" data-slot="fab" aria-label="<?= $this->text->e(t('Go to the chat example')) ?>">
        <span aria-hidden="true">↓</span>
    </a>
    <?= $this->kit->stage(ob_get_clean(), true) ?>
</section>
