<?php

use Kanboard\Plugin\Harbor\Theme\ThemeResolver;
use PHPUnit\Framework\TestCase;

class ThemeResolverTest extends TestCase
{
    /** @dataProvider themeCases */
    public function testResolvesTheme(
        ?string $userTheme,
        bool $loggedIn,
        string $guestTheme,
        string $preset,
        string $classes,
        string $colorScheme,
        string $background,
        bool $autoScript
    ): void {
        $this->assertSame(
            compact('classes', 'colorScheme', 'background', 'autoScript'),
            (new ThemeResolver())->resolve($userTheme, $loggedIn, $guestTheme, $preset)
        );
    }

    public static function themeCases(): iterable
    {
        $newburghDark = 'hsl(210 50% 6%)';
        $newburghLight = 'hsl(30 25% 97%)';
        $shadcnDark = 'oklch(.145 0 0)';
        $shadcnLight = 'oklch(1 0 0)';

        yield 'Newburgh member light' => ['light', true, 'dark', 'newburgh', 'theme-light preset-newburgh', 'light', $newburghLight, false];
        yield 'Newburgh member dark' => ['dark', true, 'light', 'newburgh', 'theme-dark preset-newburgh', 'dark', $newburghDark, false];
        yield 'Newburgh member auto' => ['auto', true, 'dark', 'newburgh', 'theme-dark preset-newburgh', 'light dark', "var(--background, $newburghDark)", true];
        yield 'Newburgh guest with core light' => ['light', false, 'dark', 'newburgh', 'theme-dark preset-newburgh', 'dark', $newburghDark, false];
        yield 'Newburgh guest with core dark' => ['dark', false, 'dark', 'newburgh', 'theme-dark preset-newburgh', 'dark', $newburghDark, false];
        yield 'Newburgh guest with core auto' => ['auto', false, 'dark', 'newburgh', 'theme-dark preset-newburgh', 'dark', $newburghDark, false];

        yield 'Shadcn member light' => ['light', true, 'dark', 'shadcn', 'theme-light preset-shadcn', 'light', $shadcnLight, false];
        yield 'Shadcn member dark' => ['dark', true, 'light', 'shadcn', 'theme-dark preset-shadcn', 'dark', $shadcnDark, false];
        yield 'Shadcn member auto' => ['auto', true, 'dark', 'shadcn', 'theme-dark preset-shadcn', 'light dark', "var(--background, $shadcnDark)", true];
        yield 'Shadcn guest with core light' => ['light', false, 'dark', 'shadcn', 'theme-dark preset-shadcn', 'dark', $shadcnDark, false];
        yield 'Shadcn guest with core dark' => ['dark', false, 'dark', 'shadcn', 'theme-dark preset-shadcn', 'dark', $shadcnDark, false];
        yield 'Shadcn guest with core auto' => ['auto', false, 'dark', 'shadcn', 'theme-dark preset-shadcn', 'dark', $shadcnDark, false];

        yield 'Newburgh guest configured light' => ['dark', false, 'light', 'newburgh', 'theme-light preset-newburgh', 'light', $newburghLight, false];
        yield 'Newburgh guest configured auto' => ['light', false, 'auto', 'newburgh', 'theme-dark preset-newburgh', 'light dark', "var(--background, $newburghDark)", true];
        yield 'Shadcn guest configured light' => ['dark', false, 'light', 'shadcn', 'theme-light preset-shadcn', 'light', $shadcnLight, false];
        yield 'Shadcn guest configured auto' => ['light', false, 'auto', 'shadcn', 'theme-dark preset-shadcn', 'light dark', "var(--background, $shadcnDark)", true];

        yield 'unknown member theme' => ['unknown', true, 'light', 'shadcn', 'theme-dark preset-shadcn', 'dark', $shadcnDark, false];
        yield 'missing member theme' => [null, true, 'light', 'newburgh', 'theme-dark preset-newburgh', 'dark', $newburghDark, false];
        yield 'unknown guest theme' => ['light', false, 'unknown', 'shadcn', 'theme-dark preset-shadcn', 'dark', $shadcnDark, false];
        yield 'unknown preset' => ['light', true, 'dark', 'unknown', 'theme-light preset-newburgh', 'light', $newburghLight, false];
    }
}
