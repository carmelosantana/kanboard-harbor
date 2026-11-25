<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;

/**
 * Guards the theme's sizing contract: every form control takes its height from
 * one token in tokens.css.
 */
class CssContractTest extends Base
{
    private function css(string $file): string
    {
        return (string) file_get_contents(__DIR__ . '/../Assets/css/' . $file);
    }

    public function testTokensDefineTheControlHeight()
    {
        $this->assertMatchesRegularExpression('/--control-height:\s*2\.25rem;/', $this->css('tokens.css'));
        $this->assertSame(0, preg_match('/--control-height\s*:/', $this->css('kit.css')), 'kit.css redeclares the control height');
    }

    public function testCoreSizesControlsFromTheToken()
    {
        $this->assertMatchesRegularExpression('/height:\s*var\(--control-height,\s*2\.25rem\)/', $this->css('kit.css'));
    }

    public function testKitVariablesHaveFallbacks(): void
    {
        $tokens = preg_replace('~/\*.*?\*/~s', '', $this->css('tokens.css'));
        preg_match_all('/(?:^|})\s*(?::root|html:root|html)\s*\{([^{}]*)\}/m', $tokens, $roots);
        preg_match_all('/(?:^|;)\s*(--[\w-]+)\s*:/m', implode("\n", $roots[1]), $alwaysDefined);

        $directory = __DIR__ . '/../Assets/css/';
        foreach ([$directory . 'kit.css', ...glob($directory . 'kit-*.css')] as $file) {
            $css = preg_replace('~/\*.*?\*/~s', '', file_get_contents($file));
            preg_match_all('/\bvar\(\s*(--[\w-]+)\s*\)/i', $css, $bare);
            // Core must stand on its own. Surface modules can use the shared root tokens.
            $exempt = basename($file) === 'kit.css' ? [] : $alwaysDefined[1];
            $this->assertSame([], array_values(array_unique(array_diff($bare[1], $exempt))), basename($file));
        }
    }

    public function testTokenStylesheetContainsOnlyRootRulesAndCriticalPaint(): void
    {
        $css = $this->css('tokens.css');
        $css = preg_replace('~/\* critical:start \*/.*?/\* critical:end \*/~s', '', $css);
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        $roots = [
            ':root', 'html', 'html.theme-light', 'html.preset-shadcn', 'html.preset-shadcn.theme-light',
            'html[data-density="compact"]:root', 'html:root', 'html:root:not(.theme-light)',
        ];

        $this->assertNotEmpty($rules);
        foreach ($rules as [, $selector, $declarations]) {
            $this->assertContains(trim($selector), $roots);
            $this->assertMatchesRegularExpression('/\A(?:\s*--[\w-]+\s*:[^;]+;)*\s*\z/', $declarations, $selector);
        }
        $this->assertSame('', trim(preg_replace('/[^{}]+\{[^{}]*\}/', '', $css)));
    }

    public function testThemeAndLoginFilesDoNotResizeControls()
    {
        foreach (['tokens.css', 'login.css'] as $file) {
            $css = $this->css($file);
            $this->assertDoesNotMatchRegularExpression('/\bmin-height:\s*2/', $css, "$file sets a control min-height");
            $this->assertDoesNotMatchRegularExpression('/input\[type="(text|password|email)"\][^{]*\{[^}]*\bpadding:/', $css, "$file pads text inputs");
        }
    }

    public function testPluginRegistersTheTokenStylesheetBeforeTheKit(): void
    {
        (new Plugin($this->container))->initialize();

        $this->assertSame([
            'plugins/Harbor/Assets/css/tokens.css',
            'plugins/Harbor/Assets/css/kit.css',
        ], array_column($this->container['hook']->getListeners('template:layout:css'), 'template'));
    }
}
