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

if ($harborAuto) {
    // The blocking script changes the class, which changes the critical background token.
    $harborBackground = "var(--background, $harborBackground)";
}
$harborAttributes = " class=\"$harborClasses\" style=\"color-scheme:$harborScheme;background:$harborBackground\"";
$harborHtml = preg_replace('/<html(?=[\s>])/i', '<html' . $harborAttributes, $harborHtml, 1);

$harborHead = "<meta name=\"color-scheme\" content=\"$harborScheme\">\n"
    . '<style id="harbor-critical">' . LayoutChain::criticalCss() . '</style>';
if ($harborAuto) {
    $harborAsset = 'Assets/js/theme-auto.js';
    $harborFile = dirname(__DIR__) . '/' . $harborAsset;
    // The auto asset is added with the theme toggle.
    $harborMtime = filemtime(is_file($harborFile) ? $harborFile : __FILE__);
    $harborSrc = $this->text->e($this->url->dir() . 'plugins/Harbor/' . $harborAsset . '?' . $harborMtime);
    $harborHead .= "\n<script src=\"$harborSrc\"></script>";
}

$harborCharset = '<meta charset="utf-8">';
$harborCharsetPosition = strpos($harborHtml, $harborCharset);
if ($harborCharsetPosition !== false) {
    $harborHtml = substr_replace($harborHtml, "\n" . $harborHead, $harborCharsetPosition + strlen($harborCharset), 0);
}

echo $harborHtml;
