<?php

namespace Kanboard\Plugin\Harbor\Theme;

final class ThemeResolver
{
    // Literal first-paint backgrounds from the critical region of Assets/css/tokens.css.
    private const BACKGROUNDS = [
        'newburgh' => ['dark' => 'hsl(210 50% 6%)', 'light' => 'hsl(30 25% 97%)'],
        'shadcn' => ['dark' => 'oklch(.145 0 0)', 'light' => 'oklch(1 0 0)'],
    ];

    public function resolve(?string $userTheme, bool $loggedIn, string $guestTheme, string $preset): array
    {
        $mode = $loggedIn ? $userTheme : $guestTheme;
        $preset = isset(self::BACKGROUNDS[$preset]) ? $preset : 'newburgh';
        $paint = $mode === 'light' ? 'light' : 'dark';
        $autoScript = $mode === 'auto';
        $background = self::BACKGROUNDS[$preset][$paint];

        return [
            'classes' => "theme-$paint preset-$preset",
            'colorScheme' => $autoScript ? 'light dark' : $paint,
            'background' => $autoScript ? "var(--background, $background)" : $background,
            'autoScript' => $autoScript,
        ];
    }
}
