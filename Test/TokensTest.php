<?php

use Kanboard\Plugin\Harbor\Test\Support\Contrast;
use PHPUnit\Framework\TestCase;

class TokensTest extends TestCase
{
    private const PALETTES = [
        ':root',
        'html.theme-light',
        'html.preset-shadcn',
        'html.preset-shadcn.theme-light',
    ];

    public function testEachPaletteDefinesTheSameTokens(): void
    {
        $required = preg_split('/\s+/', trim('
            background foreground card card-foreground popover popover-foreground
            primary primary-foreground secondary secondary-foreground muted muted-foreground
            accent accent-foreground destructive destructive-foreground border input ring
            row-line highlight success warning chart-1 chart-2 chart-3 chart-4 chart-5
            radius radius-sm radius-md radius-lg
            space-1 space-2 space-3 space-4 space-5 space-6 space-8 space-10 space-12 space-16 space-20
            motion-fast motion-base control-height density
        '));
        $palettes = $this->palettes();

        foreach ($palettes as $selector => $tokens) {
            $this->assertSame([], array_values(array_diff($required, array_keys($tokens))), $selector);
            $this->assertEqualsCanonicalizing(array_keys($palettes[':root']), array_keys($tokens), $selector);
        }
    }

    public function testTextMeetsWcagAaContrastInEachPalette(): void
    {
        $pairs = [
            ['foreground', 'background'],
            ['card-foreground', 'card'],
            ['muted-foreground', 'background'],
            ['primary-foreground', 'primary'],
            ['accent-foreground', 'accent'],
        ];

        foreach ($this->palettes() as $selector => $tokens) {
            foreach ($pairs as [$text, $surface]) {
                $ratio = Contrast::ratio($this->color($tokens, $text), $this->color($tokens, $surface));
                $this->assertGreaterThanOrEqual(
                    4.5,
                    $ratio,
                    sprintf('%s: --%s on --%s has %.2f:1 contrast', $selector, $text, $surface, $ratio)
                );
            }
        }
    }

    public function testHighlightDiffersFromMutedInEachPalette(): void
    {
        foreach ($this->palettes() as $selector => $tokens) {
            $highlight = $this->color($tokens, 'highlight');
            $muted = $this->color($tokens, 'muted');
            $this->assertNotSame($muted, $highlight, "$selector uses --muted for --highlight");
        }
    }

    public function testPillTextMeetsWcagAaContrastInEachPalette(): void
    {
        $kit = $this->kitStylesheet();
        $this->assertSame(1, preg_match('/^\[data-slot="pill"\]\h*\{([^}]*)\}/m', $kit, $pill));
        $this->assertSame(1, preg_match(
            '/background:\s*color-mix\(in srgb, var\(--c, [^)]+\) ([\d.]+)%, transparent\);/',
            $pill[1],
            $mix
        ));
        $tintWeight = (float) $mix[1] / 100;
        $this->assertSame(1, preg_match(
            '/\bcolor:\s*color-mix\(in srgb, var\(--c, [^)]+\) ([\d.]+)%, var\(--([\w-]+), [^)]+\)\);/',
            $pill[1],
            $textMix
        ));
        $textWeight = (float) $textMix[1] / 100;
        $palettes = $this->palettes();
        $failures = [];

        foreach (['neutral', 'info', 'success', 'warning', 'error'] as $tone) {
            $selector = ':is([data-slot="pill"], [data-slot="callout"])';
            if ($tone !== 'neutral') {
                $selector .= '[data-tone="' . $tone . '"]';
            }
            $this->assertSame(1, preg_match(
                '/^' . preg_quote($selector, '/') . '\h*\{\s*--c:\s*var\(--([\w-]+), [^)]+\);/m',
                $kit,
                $rule
            ));

            foreach ($palettes as $palette => $tokens) {
                $tint = $this->color($tokens, $rule[1]);
                $foreground = $this->color($tokens, $textMix[2]);
                $textLuminance = $this->mixedLuminance($tint, $foreground, $textWeight);
                foreach (['background', 'card'] as $surface) {
                    $backgroundLuminance = $this->mixedLuminance($tint, $this->color($tokens, $surface), $tintWeight);
                    $ratio = (max($textLuminance, $backgroundLuminance) + 0.05)
                        / (min($textLuminance, $backgroundLuminance) + 0.05);
                    if ($ratio < 4.5) {
                        $failures[] = sprintf('%s: %s pill on --%s has %.2f:1 contrast', $palette, $tone, $surface, $ratio);
                    }
                }
            }
        }
        $this->assertSame([], $failures, 'All pill tones must meet 4.5:1 contrast');
    }

    public function testCriticalRegionContainsOnlyFirstPaintCss(): void
    {
        $css = $this->tokenStylesheet();
        $this->assertSame(1, preg_match('~\A/\* critical:start \*/(.*?)/\* critical:end \*/~s', $css, $region));
        preg_match_all('/([^{}]+)\{([^}]*)\}/', $region[1], $rules, PREG_SET_ORDER);

        $selectors = [];
        foreach ($rules as [, $selector, $body]) {
            $selector = trim($selector);
            $selectors[] = $selector;
            if ($selector === 'html') {
                $this->assertSame('background: var(--background); color: var(--foreground);', preg_replace('/\s+/', ' ', trim($body)));
                continue;
            }
            $this->assertEqualsCanonicalizing(
                ['background', 'foreground', 'card', 'primary', 'border'],
                array_keys($this->declarations($body)),
                $selector
            );
            $this->assertSame(5, substr_count($body, ';'), $selector);
        }
        $this->assertSame([...self::PALETTES, 'html'], $selectors);
    }

    public function testColorsAreConsolidatedInTheTokenStylesheet(): void
    {
        $directory = __DIR__ . '/../Assets/css/';
        $this->assertSame(0, preg_match('/--(?:shadow[\w-]*|ring-shadow)\s*:/', $this->kitStylesheet()));
        $this->assertFileDoesNotExist($directory . 'theme-light.css');
        $this->assertFileDoesNotExist($directory . 'theme-dark.css');
    }

    public function testKitDefinesTaskAccentsForCoreColorClasses(): void
    {
        $kit = $this->kitStylesheet();
        preg_match_all('/^:root \.color-([\w-]+)\s*\{\s*--task-accent:\s*[^;]+;\s*\}/m', $kit, $matches);
        $this->assertEqualsCanonicalizing([
            'yellow', 'blue', 'green', 'purple', 'red', 'orange', 'grey', 'brown',
            'deep_orange', 'dark_grey', 'pink', 'teal', 'cyan', 'lime', 'light_green', 'amber',
        ], $matches[1]);
        $this->assertMatchesRegularExpression('/\.task-summary-container\s*\{[^}]*border-left:\s*3px solid var\(--task-accent,/', $kit);
    }

    public function testActiveSidebarRowUsesTheAccentForeground(): void
    {
        $this->assertMatchesRegularExpression(
            '/:root \.sidebar > ul li\.active a\s*\{[^}]*color:\s*var\(--accent-foreground,/',
            $this->kitStylesheet()
        );
    }

    public function testTaskSummaryLinksOverrideCoreDarkBlack(): void
    {
        $this->assertMatchesRegularExpression(
            '/:root \.task-summary-column a,\s*:root \.task-summary-column a:hover\s*\{[^}]*color:\s*var\(--foreground,/',
            $this->kitStylesheet()
        );
    }

    public function testThemeRetainsTheFocusRingForCoreControls(): void
    {
        $this->assertMatchesRegularExpression(
            '/\[tabindex\]:focus-visible\s*\{\s*outline:\s*2px solid var\(--ring,/',
            $this->kitStylesheet()
        );
    }

    public function testNoThemeSelectorKeysOnBody(): void
    {
        foreach (glob(__DIR__ . '/../Assets/css/*.css') as $file) {
            $css = preg_replace('~/\*.*?\*/~s', '', file_get_contents($file));
            $match = preg_match('/(?:\A|[{},])\s*body(?:\.theme-|:not\(\s*\.theme-)/i', $css);
            $this->assertSame(0, $match, basename($file) . ' keys a theme selector on body');
        }
    }

    private function color(array $tokens, string $token): string
    {
        $seen = [];
        while (true) {
            $this->assertArrayHasKey($token, $tokens);
            $this->assertArrayNotHasKey($token, $seen, "Circular color alias --$token");
            $seen[$token] = true;
            $value = $tokens[$token];
            if (!preg_match('/^var\(--([\w-]+)\)$/', $value, $match)) {
                return $value;
            }
            $token = $match[1];
        }
    }

    private function mixedLuminance(string $tint, string $base, float $weight): float
    {
        // Reuse the opaque contrast check's conversion before mixing in encoded sRGB.
        $linearSrgb = new ReflectionMethod(Contrast::class, 'linearSrgb');
        $encode = static fn (float $channel): float => $channel <= 0.0031308
            ? 12.92 * $channel
            : 1.055 * $channel ** (1 / 2.4) - 0.055;
        [$red, $green, $blue] = array_map(
            static function (float $tintChannel, float $baseChannel) use ($weight, $encode): float {
                $channel = $encode($tintChannel) * $weight + $encode($baseChannel) * (1 - $weight);

                return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            },
            $linearSrgb->invoke(null, $tint),
            $linearSrgb->invoke(null, $base)
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    private function palettes(): array
    {
        $css = $this->tokenStylesheet();
        $palettes = [];
        foreach (self::PALETTES as $selector) {
            $pattern = '/^\h*' . preg_quote($selector, '/') . '\h*\{([^}]*)\}/m';
            preg_match_all($pattern, $css, $blocks);
            $this->assertNotEmpty($blocks[1], "Missing palette $selector");
            $palettes[$selector] = $this->declarations(implode("\n", $blocks[1]));
        }

        return $palettes;
    }

    private function tokenStylesheet(): string
    {
        $file = __DIR__ . '/../Assets/css/tokens.css';
        $this->assertFileExists($file);

        return file_get_contents($file);
    }

    private function kitStylesheet(): string
    {
        return file_get_contents(__DIR__ . '/../Assets/css/kit.css');
    }

    private function declarations(string $block): array
    {
        preg_match_all('/^\h*--([\w-]+):\h*(\S[^;]*);/m', $block, $matches);

        return array_combine($matches[1], array_map('trim', $matches[2]));
    }
}
