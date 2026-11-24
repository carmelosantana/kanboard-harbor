<?php if ($this->user->isAdmin()): ?>
    <li>
        Harbor
        <ul>
            <li <?= $this->app->checkMenuSelection('HarborStyleGuideController', 'show', 'Harbor') ?>>
                <?= $this->url->link(t('Style guide'), 'HarborStyleGuideController', 'show', ['plugin' => 'Harbor']) ?>
            </li>
        </ul>
    </li>
<?php endif ?>
