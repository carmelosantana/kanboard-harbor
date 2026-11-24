<?php

namespace Kanboard\Plugin\Harbor\Theme;

use Kanboard\Core\Template;
use WeakMap;

final class LayoutChain
{
    /** @var WeakMap<Template, string>|null */
    private static ?WeakMap $previousPaths = null;

    public static function register(Template $template, string $wrapper): void
    {
        self::$previousPaths ??= new WeakMap();
        if (isset(self::$previousPaths[$template])) {
            return;
        }

        self::$previousPaths[$template] = $template->getTemplateFile('layout');
        $template->setTemplateOverride('layout', $wrapper);
    }

    public static function previous(Template $template): string
    {
        return self::$previousPaths[$template];
    }
}
