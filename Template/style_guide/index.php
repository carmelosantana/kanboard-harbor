<?php $route = ['plugin' => 'Harbor']; ?>
<?= $this->render('Harbor:config/style_guide', compact('query', 'matches', 'count')) ?>
<?= $this->render('Harbor:style_guide/activity') ?>
<?= $this->render('Harbor:style_guide/float') ?>
<?= $this->render('Harbor:style_guide/chat', ['action' => $this->url->to('HarborStyleGuideController', 'show', $route, 'style-guide-chat')]) ?>
<?= $this->render('Harbor:style_guide/charts') ?>
<?= $this->render('Harbor:style_guide/operations', [
    'action' => $this->url->to('HarborStyleGuideController', 'show', $route, 'style-guide-confirm'),
    'sampleLog' => $this->url->to('HarborStyleGuideController', 'show', $route + ['sample' => 'log']),
]) ?>
<?= $this->render('Harbor:style_guide/navigation', compact('from', 'to')) ?>
<?= $this->render('Harbor:style_guide/primitives') ?>
