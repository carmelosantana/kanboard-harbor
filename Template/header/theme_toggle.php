<?php

use Kanboard\Plugin\Harbor\Theme\TemplateChain;
use Kanboard\Plugin\Harbor\Theme\ThemeResolver;

if (($harborUserId = $this->user->getId()) > 0): ?>
    <?php
    $theme = $this->user->getTheme();
    $themeLabels = ['light' => t('Light'), 'dark' => t('Dark'), 'auto' => t('Auto')];
    $density = ThemeResolver::isCompact($this->model->userMetadataModel, $harborUserId) ? 'compact' : 'comfortable';
    $densityLabels = ['comfortable' => t('Comfortable'), 'compact' => t('Compact')];
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
            <div role="group" aria-label="<?= t('Density') ?>">
                <span aria-hidden="true"><?= t('Density') ?></span>
                <?php foreach ($densityLabels as $mode => $label): ?>
                    <button type="submit"
                            formaction="<?= $this->url->href('DensityController', 'set', ['plugin' => 'Harbor', 'mode' => $mode]) ?>"
                            aria-pressed="<?= $density === $mode ? 'true' : 'false' ?>">
                        <?= $label ?>
                    </button>
                <?php endforeach ?>
            </div>
        </form>
    </details>
<?php endif ?>
<?php include TemplateChain::previous($this, 'header/user_dropdown'); ?>
