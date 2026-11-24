<div class="stage harbor-auth">
    <section class="float form-login" aria-labelledby="harbor-auth-title">
        <div class="harbor-auth-brand">
            <h1 id="harbor-auth-title"><?= t('Enter your code') ?></h1>
        </div>

        <?php $flashMessage = $this->app->flashMessage() ?>
        <?php if ($flashMessage !== ''): ?>
            <div class="callout" role="status"><?= $flashMessage ?></div>
        <?php endif ?>

        <form method="post" action="<?= $this->url->href('TwoFactorController', 'check', ['user_id' => $this->user->getId()]) ?>">
            <?= $this->form->csrf() ?>

            <div data-code-fallback>
                <?= $this->form->label(t('Code'), 'code') ?>
                <?= $this->form->text('code', [], [], ['placeholder="123456"', 'autofocus', 'autocomplete="one-time-code"', 'pattern="[0-9]*"', 'inputmode="numeric"'], 'form-numeric') ?>
            </div>
            <fieldset data-code-digits hidden disabled>
                <legend><?= t('Code') ?></legend>
                <div class="harbor-code-digits">
                    <?php for ($digit = 1; $digit <= 6; $digit++): ?>
                        <input type="text" inputmode="numeric" pattern="[0-9]" required
                               aria-label="<?= $this->text->e(t('Digit %d', $digit)) ?>"
                               autocomplete="<?= $digit === 1 ? 'one-time-code' : 'off' ?>">
                    <?php endfor ?>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn btn-blue"><?= t('Check my code') ?></button>
                <?= t('or') ?>
                <?= $this->url->link(t('cancel'), 'AuthController', 'logout', [], true) ?>
            </div>
        </form>
    </section>
</div>
