<?php
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<dialog data-slot="typed-confirm" data-popup-preset="dialog" data-phrase="<?= $escape($phrase) ?>"
        aria-label="<?= $escape($title) ?>" hidden>
    <h2><?= $escape($title) ?></h2>
    <?= $bodyHtml ?>
    <form method="post" action="<?= $escape($formAction) ?>">
        <?= $this->form->csrf() ?>
        <label>
            <?= $escape(e('Type %s to confirm', $phrase)) ?>
            <input type="text" name="confirmation" data-slot="control" required
                   autocomplete="off" autocapitalize="off" spellcheck="false">
        </label>
        <div class="form-actions">
            <button type="button" class="btn" data-confirm-cancel><?= t('Cancel') ?></button>
            <button type="submit" class="btn btn-red" disabled><?= $escape($actionLabel) ?></button>
        </div>
    </form>
</dialog>
