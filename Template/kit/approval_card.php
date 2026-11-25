<section data-slot="approval-card" data-expires-at="<?= $expiresAt ?>">
    <?= $this->kit->status('pending') ?>
    <span role="timer" aria-label="<?= $this->text->e(t('Time left')) ?>"
          data-approval-countdown="<?= $this->text->e(t('Expired')) ?>"><?= $this->text->e($countdown) ?></span>
    <dl>
        <?php foreach ($details as $label => $value): ?>
            <dt><?= $this->text->e($label) ?></dt>
            <dd><?= $this->text->e($value) ?></dd>
        <?php endforeach ?>
    </dl>
    <a data-approval-action <?php if ($href !== null && $remaining > 0): ?>href="<?= $this->text->e($href) ?>"<?php else: ?>role="link" aria-disabled="true"<?php endif ?>><?= $this->text->e($actionLabel) ?></a>
</section>
