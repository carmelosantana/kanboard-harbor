<?php
$now = time();
$counts = [];
$day = new DateTimeImmutable('today -181 days');
for ($i = 0; $i < 182; ++$i, $day = $day->modify('+1 day')) {
    $counts[$day->format('Y-m-d')] = $day->format('N') <= 5 ? ($i * 17 + intdiv($i, 7)) % 13 : $i % 3;
}
$ticket = '<strong>' . $this->text->e('#7289') . '</strong>';
?>
<section>
    <h3><?= t('Activity feed') ?></h3>
    <?= $this->kit->activityRow(
        'Carmelo Santana',
        '<strong>' . $this->text->e('Carmelo') . '</strong> ' . t('moved') . ' ' . $ticket . ' ' . t('to In progress'),
        $now - 120
    ) ?>
    <?= $this->kit->activityRow(
        'Design reviewer',
        '<strong>' . $this->text->e('Design reviewer') . '</strong> ' . t('published new mockups for') . ' ' . $ticket,
        $now - 840,
        'style-guide-event'
    ) ?>
    <?= $this->kit->activityRow(
        'Scheduler',
        '<strong>' . $this->text->e('Scheduler') . '</strong> ' . t('rescheduled three tasks'),
        $now - 3600
    ) ?>
    <p><a href="#style-guide-event"><?= t('Jump to the mockup update') ?></a></p>
</section>

<section>
    <h3><?= t('Activity heatmap') ?></h3>
    <p><?= t('Task events over the past 26 weeks') ?></p>
    <?= $this->kit->heatmap($counts) ?>
</section>
