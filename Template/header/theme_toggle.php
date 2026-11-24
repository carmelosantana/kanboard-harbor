<?php if ($this->user->getId() > 0): ?>
    <?php
    $theme = $this->user->getTheme();
    $themeLabels = ['light' => t('Light'), 'dark' => t('Dark'), 'auto' => t('Auto')];
    ?>
    <details class="harbor-theme-toggle">
        <summary title="<?= $this->text->e($themeLabels[$theme] ?? t('Theme')) ?>">
            <i class="fa fa-adjust" aria-hidden="true"></i>
            <?= t('Theme') ?>
        </summary>
        <form method="post" aria-label="<?= t('Theme') ?>">
            <?= $this->form->csrf() ?>
            <?= $this->form->hidden('return_to', ['return_to' => $_SERVER['REQUEST_URI'] ?? '']) ?>
            <?php foreach ($themeLabels as $mode => $label): ?>
                <button type="submit"
                        formaction="<?= $this->url->href('ThemeController', 'set', ['plugin' => 'Harbor', 'mode' => $mode]) ?>"
                        aria-pressed="<?= $theme === $mode ? 'true' : 'false' ?>">
                    <?= $label ?>
                </button>
            <?php endforeach ?>
        </form>
    </details>
<?php endif ?>
<?= $this->render('kanboard:header/user_dropdown') ?>
