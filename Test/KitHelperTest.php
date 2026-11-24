<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;

class KitHelperTest extends Base
{
    public function testTemplatesCanRenderAllSixKitSlots(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;

        $components = [
            'pill' => $kit->pill('Current', 'success'),
            'callout' => $kit->callout('Try again', 'The request failed.', 'error'),
            'progress' => $kit->progress(3, 5, 4.5, 8),
            'skeleton' => $kit->skeleton('row'),
            'empty' => $kit->empty('No matches', 'Try another word.', ''),
            'toolbar' => $kit->toolbar([]),
        ];

        foreach ($components as $slot => $html) {
            $this->assertStringContainsString('data-slot="' . $slot . '"', $html);
        }
    }

    public function testKitStylesExposeSlotsAndPopupStates(): void
    {
        $css = file_get_contents(__DIR__ . '/../Assets/css/kit.css');

        foreach (['pill', 'callout', 'progress', 'skeleton', 'empty', 'toolbar', 'chip', 'result-count',
            'control', 'tabs', 'segmented', 'popup', 'toaster', 'toast'] as $slot) {
            $this->assertStringContainsString('[data-slot="' . $slot . '"]', $css);
        }
        foreach (['menu', 'popover', 'dialog', 'tooltip'] as $preset) {
            $this->assertStringContainsString('[data-popup-preset="' . $preset . '"]', $css);
        }
        $this->assertStringContainsString('left: var(--popup-x', $css);
        $this->assertStringContainsString('top: var(--popup-y', $css);
        $this->assertMatchesRegularExpression('/prefers-reduced-motion: reduce[^}]+\[data-slot="skeleton"\][^}]+animation: none/s', $css);
        $this->assertMatchesRegularExpression('/prefers-reduced-motion: reduce[^}]+data-starting-style[^}]+animation: none/s', $css);
    }

    public function testLayoutRendersOneEmptyPoliteToasterInsideTheBody(): void
    {
        (new Plugin($this->container))->initialize();
        $html = $this->container['template']->render('layout', [
            'no_layout' => true,
            'content_for_layout' => '<main>Page content</main>',
        ]);

        $toaster = '<div data-slot="toaster" role="status" aria-live="polite"></div>';
        $this->assertSame(1, substr_count($html, $toaster));
        $this->assertMatchesRegularExpression('~<body\b.*' . preg_quote($toaster, '~') . '\s*</body>~s', $html);
    }
}
