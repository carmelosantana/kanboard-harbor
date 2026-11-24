<?php

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
