<div data-slot="activity-row"<?= $id === '' ? '' : ' id="' . $this->text->e($id) . '"' ?>>
    <span class="kit-activity-avatar" aria-hidden="true"><?= $this->text->e($initials) ?></span>
    <div class="kit-activity-sentence"><?= $sentenceHtml ?></div>
    <time datetime="<?= $this->text->e(date(DATE_ATOM, $timestamp)) ?>"><?= $this->text->e($this->dt->datetime($timestamp)) ?></time>
</div>
