<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards the theme's sizing contract: every form control takes its height from
 * one token in shadcn-core.css, and the per-theme files only carry colors.
 */
class CssContractTest extends TestCase
{
    private function css(string $file): string
    {
        return (string) file_get_contents(__DIR__ . '/../Assets/css/' . $file);
    }

    public function testCoreDefinesTheControlHeightToken()
    {
        $this->assertMatchesRegularExpression('/--control-height:\s*2\.25rem;/', $this->css('shadcn-core.css'));
    }

    public function testCoreSizesControlsFromTheToken()
    {
        $this->assertMatchesRegularExpression('/height:\s*var\(--control-height\)/', $this->css('shadcn-core.css'));
    }

    public function testThemeAndLoginFilesDoNotResizeControls()
    {
        foreach (['shadcn-light.css', 'shadcn-dark.css', 'shadcn-login.css'] as $file) {
            $css = $this->css($file);
            $this->assertDoesNotMatchRegularExpression('/\bmin-height:\s*2/', $css, "$file sets a control min-height");
            $this->assertDoesNotMatchRegularExpression('/input\[type="(text|password|email)"\][^{]*\{[^}]*\bpadding:/', $css, "$file pads text inputs");
        }
    }
}
