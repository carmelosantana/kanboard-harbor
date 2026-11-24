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
        $kit = file_get_contents($directory . 'kit.css');
        $this->assertSame(0, preg_match('/^\h*--(?:shadow[\w-]*|ring-shadow):/m', $kit));
        $this->assertFileDoesNotExist($directory . 'theme-light.css');
        $this->assertFileDoesNotExist($directory . 'theme-dark.css');
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

    private function declarations(string $block): array
    {
        preg_match_all('/^\h*--([\w-]+):\h*(\S[^;]*);/m', $block, $matches);

        return array_combine($matches[1], array_map('trim', $matches[2]));
    }
}
