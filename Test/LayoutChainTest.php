<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Base as CoreBase;
use Kanboard\Plugin\Harbor\Plugin;
use Kanboard\Plugin\Harbor\Theme\LayoutChain;
use Kanboard\Plugin\Harbor\Theme\ThemeResolver;
use KanboardTests\units\Base;

class FakeStewardLayoutHelper extends CoreBase
{
    public string $previous;
}

class LayoutChainTest extends Base
{
    public function testRegistrationCapturesTheCurrentLayoutBeforeOverridingIt(): void
    {
        $template = $this->container['template'];
        $template->setTemplateOverride('layout', 'Harbor:../Test/fixtures/steward-layout');
        $previous = $template->getTemplateFile('layout');

        LayoutChain::register($template, 'Harbor:layout');

        $this->assertSame($previous, LayoutChain::previous($template));
        $this->assertSame($template->getTemplateFile('Harbor:layout'), $template->getTemplateFile('layout'));
    }

    public function testDarkGuestLayoutHasOneThemeAndCriticalCssBeforeStylesheets(): void
    {
        $this->registerSteward();
        $this->registerHarbor();
        $html = $this->renderLayout();

        $this->assertSame(1, preg_match_all('/<html\b[^>]*>/', $html, $tags));
        $this->assertSame(1, substr_count($tags[0][0], 'class="'));
        $this->assertSame(1, substr_count($tags[0][0], 'style="'));
        $this->assertStringContainsString('class="theme-dark preset-newburgh"', $tags[0][0]);
        $this->assertStringContainsString('style="color-scheme:dark;background:hsl(210 50% 6%)"', $tags[0][0]);
        $this->assertStringContainsString(
            "<meta charset=\"utf-8\">\n<meta name=\"color-scheme\" content=\"dark\">\n<style id=\"harbor-critical\">",
            $html
        );
        $this->assertSame(1, substr_count($html, 'id="harbor-critical"'));
        $this->assertSame(1, substr_count($html, 'name="color-scheme"'));
        $this->assertStringContainsString(trim($this->criticalRegion()), $html);
        $this->assertStringContainsString('<link rel="stylesheet"', $html);
        $this->assertLessThan(strpos($html, '<link rel="stylesheet"'), strpos($html, 'id="harbor-critical"'));
        $this->assertStringNotContainsString('theme-auto.js', $html);
    }

    /** @dataProvider loadOrders */
    public function testHarborAndStewardLayoutsChainInEitherLoadOrder(bool $stewardFirst): void
    {
        if ($stewardFirst) {
            $this->registerSteward();
            $this->registerHarbor();
        } else {
            $this->registerHarbor();
            $this->registerSteward();
        }

        $html = $this->renderLayout();

        $this->assertMatchesRegularExpression('~<title>\s*Steward Site\s*</title>~', $html);
        $this->assertStringContainsString('class="theme-dark preset-newburgh"', $html);
        $this->assertSame(1, substr_count($html, '<html'));
        $this->assertStringContainsString('<main>Page content</main>', $html);
    }

    public static function loadOrders(): array
    {
        return ['Steward first' => [true], 'Harbor first' => [false]];
    }

    /** @dataProvider memberThemes */
    public function testMemberUsesTheCoreThemeAndConfiguredPreset(string $mode, string $class, string $scheme): void
    {
        $member = $this->container['userModel']->getById(1);
        $member['theme'] = $mode;
        $this->container['userSession']->initialize($member);
        $this->container['configModel']->save(['harbor_preset' => 'shadcn', 'harbor_guest_theme' => 'light']);
        $this->registerHarbor();

        $html = $this->renderLayout();
        $background = (new ThemeResolver())->resolve($mode, true, 'light', 'shadcn')['background'];

        $this->assertStringContainsString("class=\"$class preset-shadcn\"", $html);
        $this->assertStringContainsString("style=\"color-scheme:$scheme;background:$background\"", $html);
        $this->assertStringContainsString("<meta name=\"color-scheme\" content=\"$scheme\">", $html);
        $this->assertSame($mode === 'auto' ? 1 : 0, substr_count($html, 'theme-auto.js'));
    }

    public static function memberThemes(): array
    {
        return [
            'light' => ['light', 'theme-light', 'light'],
            'dark' => ['dark', 'theme-dark', 'dark'],
            'auto' => ['auto', 'theme-dark', 'light dark'],
        ];
    }

    public function testAutoLayoutLoadsOneBlockingScriptImmediatelyAfterCriticalCss(): void
    {
        $this->container['configModel']->save(['harbor_guest_theme' => 'auto']);
        $this->registerHarbor();
        $script = __DIR__ . '/../Assets/js/theme-auto.js';

        $this->assertFileExists($script);
        $html = $this->renderLayout();

        $this->assertStringContainsString('<meta name="color-scheme" content="light dark">', $html);
        $this->assertStringContainsString('style="color-scheme:light dark;background:var(--background, hsl(210 50% 6%))"', $html);
        $this->assertMatchesRegularExpression(
            '~</style>\n<script src="[^"]*plugins/Harbor/Assets/js/theme-auto\.js\?' . filemtime($script) . '"></script>~',
            $html
        );
        $this->assertSame(1, substr_count($html, 'theme-auto.js'));
    }

    public function testWithoutCharsetTheCriticalBlockFollowsTheOpeningHead(): void
    {
        $previous = tempnam(sys_get_temp_dir(), 'harbor-layout-');
        try {
            file_put_contents($previous, '<html><head><title>Without charset</title></head><body></body></html>');
            $this->registerSteward();
            $this->container['template']->stewardFixture->previous = $previous;
            $this->registerHarbor();

            $html = $this->renderLayout();

            $this->assertStringContainsString(
                "<head>\n<meta name=\"color-scheme\" content=\"dark\">\n<style id=\"harbor-critical\">",
                $html
            );
            $this->assertLessThan(strpos($html, '<title>'), strpos($html, '</style>'));
        } finally {
            unlink($previous);
        }
    }

    public function testResolverBackgroundMatchesCriticalCssForEveryPalette(): void
    {
        $css = $this->criticalRegion();
        $resolver = new ThemeResolver();
        $palettes = [
            [':root', 'newburgh', 'dark'],
            ['html.theme-light', 'newburgh', 'light'],
            ['html.preset-shadcn', 'shadcn', 'dark'],
            ['html.preset-shadcn.theme-light', 'shadcn', 'light'],
        ];

        foreach ($palettes as [$selector, $preset, $mode]) {
            $pattern = '/^' . preg_quote($selector, '/') . '\s*\{[^}]*--background:\s*([^;]+);/m';
            $this->assertSame(1, preg_match($pattern, $css, $match), $selector);
            $this->assertSame(trim($match[1]), $resolver->resolve($mode, true, 'dark', $preset)['background'], $selector);
        }
    }

    public function testFailedPredecessorDiscardsHarborsBufferAndRethrows(): void
    {
        $this->registerSteward();
        $this->registerHarbor();
        $level = ob_get_level();

        try {
            $this->container['template']->render('layout', ['throwInSteward' => true]);
            $this->fail('Expected the previous wrapper to throw');
        } catch (RuntimeException $exception) {
            $this->assertSame('Steward fixture failed', $exception->getMessage());
            // Core Template::render still owns its outer buffer.
            $this->assertSame($level + 1, ob_get_level());
            $this->assertSame('', ob_get_contents());
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    public function testLegacyPreloadIsRemoved(): void
    {
        $this->registerHarbor();

        $this->assertSame([], $this->container['hook']->getListeners('template:layout:head'));
        $this->assertFileDoesNotExist(__DIR__ . '/../Template/layout/head.php');
        $this->assertFileDoesNotExist(__DIR__ . '/../Assets/js/theme-preload.js');
    }

    private function registerSteward(): void
    {
        $this->container['helper']->register('stewardFixture', FakeStewardLayoutHelper::class);
        $template = $this->container['template'];
        $template->stewardFixture->previous = $template->getTemplateFile('layout');
        $template->setTemplateOverride('layout', 'Harbor:../Test/fixtures/steward-layout');
    }

    private function registerHarbor(): void
    {
        (new Plugin($this->container))->initialize();
    }

    private function renderLayout(): string
    {
        return $this->container['template']->render('layout', [
            'no_layout' => true,
            'content_for_layout' => '<main>Page content</main>',
        ]);
    }

    private function criticalRegion(): string
    {
        $css = file_get_contents(__DIR__ . '/../Assets/css/tokens.css');
        $this->assertSame(1, preg_match('~/\* critical:start \*/(.*?)/\* critical:end \*/~s', $css, $match));
        return trim($match[1]);
    }
}
