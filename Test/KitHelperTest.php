<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Controller\Runner;
use Kanboard\Core\Http\Response;
use Kanboard\Core\Security\Role;
use Kanboard\Core\Session\SessionManager;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;

class KitHelperTest extends Base
{
    public function testNonAdminGetsA403FromTheStyleGuide(): void
    {
        $response = $this->prepareStyleGuideRequest(Role::APP_USER);
        $response->expects($this->once())->method('html')->with(
            $this->stringContains('Access Forbidden'),
            403
        );

        (new Runner($this->container))->execute();
    }

    public function testAdminCanOpenTheStyleGuideFromTheSettingsSidebar(): void
    {
        $response = $this->prepareStyleGuideRequest(Role::APP_ADMIN);
        $response->expects($this->once())->method('html')->with($this->callback(function (string $html): bool {
            $this->assertStringContainsString('Style guide', $html);
            $this->assertMatchesRegularExpression('~<a\b[^>]*\bhref="[^"]*Harbor[^"]*"[^>]*>Style guide</a>~', $html);
            foreach (['pill', 'callout', 'progress', 'skeleton', 'empty', 'toolbar'] as $slot) {
                $this->assertStringContainsString('data-slot="' . $slot . '"', $html);
            }

            return true;
        }));

        (new Runner($this->container))->execute();
    }

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

    public function testSubtaskProgressCombinesCompletionWithTheTimeBudget(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;

        $over = $kit->progress(4, 6, 9.2, 6);
        $this->assertStringContainsString('data-state="over" style="--pct:100%"', $over);
        $this->assertStringContainsString('4 of 6 subtasks', $over);
        $this->assertStringContainsString('9.2h / 6h · over budget', $over);

        $measured = $kit->progress(3, 5, 4.5, 8);
        $this->assertStringContainsString('data-state="measured" style="--pct:60%"', $measured);
        $this->assertStringContainsString('aria-valuenow="60"', $measured);
        $this->assertStringContainsString('data-state="done" style="--pct:100%"', $kit->progress(7, 7, 3, 4));
        $this->assertStringContainsString('data-state="measured" style="--pct:0%"', $kit->progress(0, 0, 0, 0));

        $loading = $kit->progress(0, null, 0, 0);
        $this->assertStringContainsString('data-state="indeterminate"', $loading);
        $this->assertStringNotContainsString('aria-valuenow', $loading);
    }

    public function testKitEscapesUserControlledTextAndAttributes(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $input = '"><img src=x onerror=alert(1)>';
        $escaped = '&quot;&gt;&lt;img src=x onerror=alert(1)&gt;';

        $components = [
            [$kit->pill($input, $input), 2],
            [$kit->callout($input, $input, $input), 3],
            [$kit->empty($input, $input, $input), 3],
            [$kit->toolbar([
                'query' => $input,
                'searchName' => $input,
                'searchLabel' => $input,
                'submitLabel' => $input,
                'params' => [$input => $input],
                'selects' => [['name' => $input, 'label' => $input, 'value' => $input, 'options' => [$input => $input]]],
                'chips' => [['name' => $input, 'label' => $input, 'value' => $input, 'checked' => true]],
                'resultCount' => $input,
            ]), 15],
        ];

        foreach ($components as [$html, $count]) {
            $this->assertStringNotContainsString('<img', $html);
            $this->assertSame($count, substr_count($html, $escaped));
        }

        $this->assertStringContainsString('data-kind="row"', $kit->skeleton($input));
        $button = '<button type="button">Clear filters</button>';
        $this->assertStringContainsString($button, $kit->empty('No matches', 'Try another word.', static fn () => $button));
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

    private function prepareStyleGuideRequest(string $role): Response
    {
        (new Plugin($this->container))->initialize();
        $userId = $role === Role::APP_ADMIN ? 1 : $this->container['userModel']->create([
            'username' => 'kit-member',
            'role' => $role,
        ]);
        $user = $this->container['userModel']->getById($userId);
        $this->container['userSession']->initialize($user);
        $this->container['sessionManager'] = $this->createMock(SessionManager::class);
        $this->container['request']->setParams([
            'controller' => 'HarborStyleGuideController',
            'action' => 'show',
            'plugin' => 'Harbor',
        ]);
        $this->container['router']->dispatch();

        $response = $this->createMock(Response::class);
        $this->container['response'] = $response;

        return $response;
    }
}
