<?php

namespace Kanboard\Plugin\Harbor\Theme;

use Kanboard\Model\UserMetadataModel;

final class ThemeResolver
{
    public const DENSITY_KEY = 'harbor_density';

    // Literal first-paint backgrounds from the critical region of Assets/css/tokens.css.
    private const BACKGROUNDS = [
        'newburgh' => ['dark' => 'hsl(210 50% 6%)', 'light' => 'hsl(30 25% 97%)'],
        'shadcn' => ['dark' => 'oklch(.145 0 0)', 'light' => 'oklch(1 0 0)'],
    ];

    public static function isCompact(UserMetadataModel $metadata, int $userId): bool
    {
        return $userId > 0 && $metadata->get($userId, self::DENSITY_KEY) === 'compact';
    }

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
