<article data-slot="chat-message" data-from="<?= $self ? 'self' : 'other' ?>">
    <header class="kit-chat-byline">
        <strong><?= $this->text->e($author) ?></strong>
        <span><?= $this->text->e($time) ?></span>
    </header>
    <?php foreach ($toolChips as $chip): ?>
        <span class="float" data-slot="tool-chip" data-kind="pill"><?= $this->text->e($chip) ?></span>
    <?php endforeach ?>
    <div class="float kit-chat-body"><?= $bodyHtml ?></div>
</article>
