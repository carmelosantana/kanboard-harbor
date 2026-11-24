<?php

use Kanboard\Plugin\Harbor\Theme\LayoutChain;
use Kanboard\Plugin\Harbor\Theme\ThemeResolver;

ob_start();
try {
    include LayoutChain::previous($this);
} catch (Throwable $exception) {
    ob_end_clean();
    throw $exception;
}
$harborHtml = ob_get_clean();

$harborTheme = (new ThemeResolver())->resolve(
    $this->user->getTheme(),
    $this->user->getId() > 0,
    $this->app->config('harbor_guest_theme', 'dark'),
    $this->app->config('harbor_preset', 'newburgh')
);
['classes' => $harborClasses, 'colorScheme' => $harborScheme, 'background' => $harborBackground,
    'autoScript' => $harborAuto] = $harborTheme;

$harborAttributes = " class=\"$harborClasses\" style=\"color-scheme:$harborScheme;background:$harborBackground\"";
$harborHtml = preg_replace('/<html(?=[\s>])/i', '<html' . $harborAttributes, $harborHtml, 1);

$harborHead = "<meta name=\"color-scheme\" content=\"$harborScheme\">\n"
    . '<style id="harbor-critical">' . LayoutChain::criticalCss() . '</style>';
if ($harborAuto) {
    $harborAsset = 'Assets/js/theme-auto.js';
    $harborFile = dirname(__DIR__) . '/' . $harborAsset;
    $harborMtime = filemtime($harborFile);
    $harborSrc = $this->text->e($this->url->dir() . 'plugins/Harbor/' . $harborAsset . '?' . $harborMtime);
    $harborHead .= "\n<script src=\"$harborSrc\"></script>";
}

if (preg_match('/<meta charset="utf-8">/i', $harborHtml, $harborMatch, PREG_OFFSET_CAPTURE)
    || preg_match('/<head\b[^>]*>/i', $harborHtml, $harborMatch, PREG_OFFSET_CAPTURE)) {
    [$harborTag, $harborOffset] = $harborMatch[0];
    $harborHtml = substr_replace($harborHtml, "\n" . $harborHead, $harborOffset + strlen($harborTag), 0);
}

echo $harborHtml;
