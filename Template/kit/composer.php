<form class="float" data-slot="composer" data-kind="pill" method="post" action="<?= $this->text->e($formAction) ?>">
    <?= $this->form->csrf() ?>
    <textarea name="message" rows="1" aria-label="<?= $this->text->e(e('Message')) ?>"
              placeholder="<?= $this->text->e($placeholder) ?>" required></textarea>
    <button type="submit" data-slot="icon-button" data-kind="primary" aria-label="<?= $this->text->e(e('Send')) ?>">
        <span aria-hidden="true">↑</span>
    </button>
</form>
