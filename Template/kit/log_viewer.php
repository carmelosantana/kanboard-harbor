<section data-slot="log-viewer" data-sse-url="<?= $this->text->e($sseUrl) ?>">
    <strong><?= $this->text->e($title) ?></strong>
    <span data-log-count="<?= $this->text->e($lineCount) ?>"><?= $this->text->e(sprintf($lineCount, 0)) ?></span>
    <div role="group" aria-label="<?= $this->text->e(t('Log mode')) ?>">
        <button type="button" data-log-follow aria-pressed="true"><?= $this->text->e(t('Follow')) ?></button>
        <button type="button" data-log-pause aria-pressed="false"><?= $this->text->e(t('Pause')) ?></button>
    </div>
    <pre role="log" aria-live="off" aria-label="<?= $this->text->e($title) ?>" tabindex="0"></pre>
    <span role="status" data-log-status="<?= $this->text->e(t('Disconnected')) ?>"></span>
    <button type="button" data-log-jump hidden><?= $this->text->e(t('Jump to latest')) ?></button>
</section>
