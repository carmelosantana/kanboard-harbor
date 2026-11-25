<?php

use PHPUnit\Framework\TestCase;

class BoardCssTest extends TestCase
{
    private array $rules;

    protected function setUp(): void
    {
        $this->rules = $this->readRules('kit-board.css');
    }

    public function testColumnsHaveNoPanels(): void
    {
        foreach (['.board-column', 'td[class*="board-column-"]', '.board-task-list'] as $selector) {
            $rule = $this->rules['html #board ' . $selector] ?? [];
            $this->assertSame('transparent', $rule['background'] ?? null, $selector);
            $this->assertSame('none', $rule['border'] ?? null, $selector);
        }
    }

    public function testColumnHeadersAreStickyPills(): void
    {
        $rule = $this->rules['html #board .board-column-header'] ?? [];
        $this->assertSame('sticky', $rule['position'] ?? null);
        $this->assertSame('0', $rule['top'] ?? null);
        $this->assertSame('var(--radius-full, 999px)', $rule['border-radius'] ?? null);
    }

    public function testLaneTintIsLimitedToHoverAndDropTargets(): void
    {
        $tint = 'color-mix(in oklch, var(--foreground) 3%, transparent)';
        $this->assertSame([
            'html #board .board-task-list:hover',
            'html #board .board-task-list.board-task-list-drop-target',
            'html #board .board-task-list:has(> .draggable-placeholder)',
        ], $this->selectorsUsing($tint));
    }

    public function testCardsRestOnOneLowShadowAndAnEdge(): void
    {
        $rule = $this->rules['html .task-board'] ?? [];
        $this->assertSame('var(--shadow-xs, 0 1px 2px rgb(0 0 0 / 5%))', $rule['box-shadow'] ?? null);
        $this->assertMatchesRegularExpression('/^1px solid var\(--row-line, .+\)$/', $rule['border'] ?? '');
    }

    public function testDeepShadowIsLimitedToHoverSelectionAndDrag(): void
    {
        $this->assertSame([
            'html .task-board:hover',
            'html .task-board.draggable-item-selected',
            'html .task-board.ui-sortable-helper',
        ], $this->selectorsUsing('var(--shadow-lg,'));
    }

    public function testOverLimitColorIsScopedToTheHeaderPill(): void
    {
        $header = 'html #board .board-column-header[data-over-limit]';
        $this->assertArrayHasKey('background-color', $this->rules[$header] ?? []);
        $this->assertSame([$header], array_values(array_filter(
            array_keys($this->rules),
            static fn (string $selector): bool => str_contains($selector, '[data-over-limit]')
        )));
        $this->assertSame('transparent', $this->rules['html .board-task-list-limit']['background'] ?? null);
    }

    public function testCollapsedColumnsHideTheCardListAndKeepTheHeader(): void
    {
        $this->assertSame('none', $this->rules['html #board .board-column-task-collapsed > .board-column-expanded']['display'] ?? null);
        $this->assertSame('block', $this->rules['html #board .board-column-header-collapsed > .board-column-collapsed']['display'] ?? null);
        $this->assertSame('inline-flex', $this->rules['html #board .board-column-expand']['display'] ?? null);
        $this->assertSame('none', $this->rules['html #board .board-column-collapsed .board-rotation-wrapper[hidden]']['display'] ?? null);
    }

    public function testCompactDensityReducesCardPadding(): void
    {
        $this->assertSame(
            'calc(var(--space-3, 0.75rem) * var(--density, 1))',
            $this->rules['html .task-board']['padding'] ?? null
        );

        $tokens = $this->readRules('tokens.css');
        // Match the Shadcn light selector's specificity so compact wins in all palettes.
        $compact = $tokens['html[data-density="compact"]:root']['--density'] ?? '';
        $this->assertTrue(is_numeric($compact) && (float) $compact > 0 && (float) $compact < 1);
    }

    public function testLightModeKeepsTheEdgeWithNoRestingShadow(): void
    {
        $rule = $this->rules['html.theme-light .task-board'] ?? [];
        $this->assertSame('none', $rule['box-shadow'] ?? null);
        $this->assertMatchesRegularExpression('/^var\(--row-line, .+\)$/', $rule['border-color'] ?? '');
    }

    private function selectorsUsing(string $value): array
    {
        return array_keys(array_filter($this->rules, static function (array $declarations) use ($value): bool {
            foreach ($declarations as $declaration) {
                if (str_contains($declaration, $value)) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function readRules(string $file): array
    {
        $css = file_get_contents(__DIR__ . '/../Assets/css/' . $file);
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $blocks, PREG_SET_ORDER);
        $rules = [];

        foreach ($blocks as [, $selectors, $body]) {
            preg_match_all('/([\w-]+)\s*:\s*([^;]+);/', $body, $declarations, PREG_SET_ORDER);
            foreach (explode(',', $selectors) as $selector) {
                $selector = preg_replace('/\s+/', ' ', trim($selector));
                foreach ($declarations as [, $name, $value]) {
                    $rules[$selector][$name] = trim($value);
                }
            }
        }

        return $rules;
    }
}
