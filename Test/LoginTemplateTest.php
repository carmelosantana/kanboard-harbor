<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Http\Router;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;
use Symfony\Contracts\EventDispatcher\Event;

class LoginTemplateTest extends Base
{
    protected function setUp(): void
    {
        parent::setUp();
        (new Plugin($this->container))->initialize();
    }

    public function testLoginRendersCoreFieldsAndHooks(): void
    {
        $this->container['hook']->on('template:auth:login-form:before', ['template' => 'app/forbidden']);
        $this->container['hook']->on('template:auth:login-form:after', ['template' => 'app/notfound']);
        $this->container['configModel']->save(['password_reset' => '1']);

        $html = $this->container['template']->render('auth/index', [
            'values' => ['username' => 'Carmelo'],
            'errors' => ['login' => 'Invalid <credentials>'],
            'captcha' => true,
        ]);

        $this->assertStringContainsString('class="stage harbor-auth"', $html);
        $this->assertStringContainsString('class="float form-login"', $html);
        $this->assertStringContainsString('<h1 id="harbor-auth-title">Sign in</h1>', $html);
        foreach (['username', 'password', 'remember_me', 'csrf_token', 'captcha'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html);
        }
        $this->assertStringContainsString('CaptchaController', $html);
        $this->assertStringContainsString('PasswordResetController', $html);
        $this->assertStringContainsString('class="callout', $html);
        $this->assertStringContainsString('Invalid &lt;credentials&gt;', $html);
        $this->assertLessThan(strpos($html, '<form'), strpos($html, 'Access Forbidden'));
        $this->assertGreaterThan(strpos($html, '</form>'), strpos($html, 'this information in my database'));
    }

    public function testTwoFactorKeepsCorePostAndTheSingleFieldFallback(): void
    {
        $this->container['flash']->failure('Invalid code');
        $html = $this->container['template']->render('twofactor/check');
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($document);

        $this->assertStringContainsString('Enter your code', $html);
        $this->assertStringContainsString('Invalid code', $html);
        $this->assertStringContainsString('class="callout', $html);
        $this->assertSame(1, $xpath->query('//form[@method="post" and contains(@action,"TwoFactorController") and contains(@action,"action=check")]')->length);
        $this->assertSame(1, $xpath->query('//form//input[@name="code" and @type="text" and not(@hidden) and not(@disabled)]')->length);
        $this->assertSame(6, $xpath->query('//form//fieldset[@data-code-digits and @hidden and @disabled]//input[not(@name) and @inputmode="numeric"]')->length);
        $this->assertSame(1, $xpath->query('//form//input[@name="csrf_token"]')->length);
        $this->assertStringContainsString('action=logout', $html);
    }

    /** @dataProvider guestThemes */
    public function testGuestThemeIsPaintedBeforeStylesheets(string $theme): void
    {
        $this->container['configModel']->save(['harbor_guest_theme' => $theme]);
        $html = $this->container['helper']->layout->app('auth/index', [
            'values' => [],
            'errors' => [],
            'no_layout' => true,
        ]);

        $this->assertStringContainsString('<html class="theme-'.$theme.' preset-newburgh" style="color-scheme:'.$theme.';background:', $html);
        $this->assertMatchesRegularExpression('/<style id="harbor-critical">.*<link rel="stylesheet"/s', $html);
    }

    public static function guestThemes(): array
    {
        return [['dark'], ['light']];
    }

    /** @dataProvider controllerCases */
    public function testAuthAssetsAreRegisteredAfterRouting(string $controller, string $action, bool $css, bool $js): void
    {
        $router = $this->createMock(Router::class);
        $router->method('getController')->willReturn($controller);
        $router->method('getAction')->willReturn($action);
        $this->container['router'] = $router;

        $hook = $this->container['helper']->hook;
        $this->assertStringNotContainsString('Harbor/Assets/css/login.css', $hook->asset('css', 'template:layout:css'));

        $this->dispatcher->dispatch(new Event(), 'app.bootstrap');

        $this->assertSame($css, str_contains($hook->asset('css', 'template:layout:css'), 'Harbor/Assets/css/login.css'));
        $this->assertSame($js, str_contains($hook->asset('js', 'template:layout:js'), 'Harbor/Assets/js/login-code.js'));
    }

    public static function controllerCases(): array
    {
        return [
            ['AuthController', 'login', true, false],
            ['TwoFactorController', 'code', true, true],
            ['TwoFactorController', 'index', true, false],
            ['DashboardController', 'show', false, false],
        ];
    }
}
