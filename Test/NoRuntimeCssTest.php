<?php

use PHPUnit\Framework\TestCase;

class NoRuntimeCssTest extends TestCase
{
    public function testHarborScriptsDoNotInjectCss(): void
    {
        $files = glob(__DIR__ . '/../Assets/js/*.js');
        $this->assertNotEmpty($files);
        $runtimeCss = <<<'REGEX'
~createElement\s*\(\s*(['"])style\1|\.\s*cssText\b|\binsertRule\b~
REGEX;

        foreach ($files as $file) {
            $found = preg_match($runtimeCss, file_get_contents($file), $match);
            $this->assertSame(0, $found, basename($file) . ' injects runtime CSS via ' . ($match[0] ?? ''));
        }
    }

    public function testKitStylesheetOwnsTheLegacyAnimationsAndTableScrolling(): void
    {
        $css = file_get_contents(__DIR__ . '/../Assets/css/kit.css');
        foreach ([
            'tooltipFadeIn', 'backdropFadeIn', 'modalSlideIn', 'modalSlideOut',
            'dropdownSlideIn', 'toastSlideIn', 'toastSlideOut',
        ] as $animation) {
            $this->assertStringContainsString('@keyframes ' . $animation . ' {', $css);
        }

        $this->assertMatchesRegularExpression('/\.table-wrap\s*\{[^}]*overflow-x:\s*auto\s*;/', $css);
        $this->assertMatchesRegularExpression('/\.table-list\s*\{[^}]*display:\s*block\s*;[^}]*overflow-x:\s*auto\s*;/', $css);
    }
}
