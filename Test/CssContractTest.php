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
        $this->assertMatchesRegularExpression('/height:\s*var\(--control-height\)/', $this->css('kit.css'));
    }

    public function testThemeAndLoginFilesDoNotResizeControls()
    {
        foreach (['tokens.css', 'login.css'] as $file) {
            $css = $this->css($file);
            $this->assertDoesNotMatchRegularExpression('/\bmin-height:\s*2/', $css, "$file sets a control min-height");
            $this->assertDoesNotMatchRegularExpression('/input\[type="(text|password|email)"\][^{]*\{[^}]*\bpadding:/', $css, "$file pads text inputs");
        }
    }

    public function testPluginRegistersTheTokenStylesheetBeforeTheKitAndLogin(): void
    {
        (new Plugin($this->container))->initialize();

        $this->assertSame([
            'plugins/Harbor/Assets/css/tokens.css',
            'plugins/Harbor/Assets/css/kit.css',
            'plugins/Harbor/Assets/css/login.css',
        ], array_column($this->container['hook']->getListeners('template:layout:css'), 'template'));
    }
}
