<?php

if (!empty($throwInSteward)) {
    echo 'Partial layout';
    throw new RuntimeException('Steward fixture failed');
}

$page_title ??= 'Steward Site';
ob_start();
try {
    include $this->stewardFixture->previous;
} catch (Throwable $exception) {
    ob_end_clean();
    throw $exception;
}
echo ob_get_clean();
