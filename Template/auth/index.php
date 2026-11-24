<?php
try {
    $branding = $this->helper->stewardBranding;
} catch (\InvalidArgumentException $e) {
    $branding = null;
}
$logo = $branding?->logoUrl() ?? '';
$siteName = $branding?->siteName() ?: 'Kanboard';
?>
<div class="stage harbor-auth">
    <section class="float form-login" aria-labelledby="harbor-auth-title">
        <div class="harbor-auth-brand">
            <?php if ($logo !== ''): ?>
                <img src="<?= $logo ?>" alt="<?= $this->text->e($siteName) ?>">
            <?php else: ?>
                <p><?= $this->text->e($siteName) ?></p>
            <?php endif ?>
            <h1 id="harbor-auth-title"><?= t('Sign in') ?></h1>
        </div>

        <?= $this->hook->render('template:auth:login-form:before') ?>

        <?php if (isset($errors['login'])): ?>
            <p class="callout callout-error" role="alert"><?= $this->text->e($errors['login']) ?></p>
        <?php endif ?>

        <?php if (! HIDE_LOGIN_FORM): ?>
        <form aria-labelledby="harbor-auth-title" method="post" action="<?= $this->url->href('AuthController', 'check') ?>">

            <?= $this->form->csrf() ?>

            <?= $this->form->label(t('Username'), 'username') ?>
            <?= $this->form->text('username', $values, $errors, array('autofocus', 'required', 'autocomplete="username"')) ?>

            <?= $this->form->label(t('Password'), 'password') ?>
            <?= $this->form->password('password', $values, $errors, array('required', 'autocomplete="current-password"')) ?>

            <?php if (isset($captcha) && $captcha): ?>
                <?= $this->form->label(t('Enter the text below'), 'captcha') ?>
                <img src="<?= $this->url->href('CaptchaController', 'image') ?>" alt="Captcha">
                <?= $this->form->text('captcha', array(), $errors, array('required')) ?>
            <?php endif ?>

            <?php if (REMEMBER_ME_AUTH): ?>
                <?= $this->form->checkbox('remember_me', t('Remember Me'), 1, true) ?>
            <?php endif ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-blue"><?= t('Sign in') ?></button>
            </div>
            <?php if ($this->app->config('password_reset') == 1): ?>
                <div class="reset-password">
                    <?= $this->url->link(t('Forgot password?'), 'PasswordResetController', 'create') ?>
                </div>
            <?php endif ?>
        </form>
        <?php endif ?>

        <?= $this->hook->render('template:auth:login-form:after') ?>
    </section>
</div>
