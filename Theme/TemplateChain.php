<?php

namespace Kanboard\Plugin\Harbor\Theme;

use Kanboard\Core\Template;
use WeakMap;

final class TemplateChain
{
    /** @var WeakMap<Template, array<string, string>>|null */
    private static ?WeakMap $previousPaths = null;

    public static function register(Template $template, string $name, string $wrapper): void
    {
        self::$previousPaths ??= new WeakMap();
        $paths = self::$previousPaths[$template] ?? [];
        if (isset($paths[$name])) {
            return;
        }

        $paths[$name] = $template->getTemplateFile($name);
        self::$previousPaths[$template] = $paths;
        $template->setTemplateOverride($name, $wrapper);
    }

    public static function previous(Template $template, string $name): string
    {
        return self::$previousPaths[$template][$name];
    }

    public static function criticalCss(): string
    {
        static $criticalCss;
        if ($criticalCss === null) {
            $tokens = file_get_contents(__DIR__ . '/../Assets/css/tokens.css');
            preg_match('~/\* critical:start \*/(.*?)/\* critical:end \*/~s', $tokens, $match);
            $criticalCss = trim($match[1]);
        }

        return $criticalCss;
    }
}
