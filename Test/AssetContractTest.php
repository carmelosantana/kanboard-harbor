<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Http\Router;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;
use Symfony\Contracts\EventDispatcher\Event;

class AssetContractTest extends Base
{
    private const GLOBAL_CSS = [
        'plugins/Harbor/Assets/css/tokens.css',
        'plugins/Harbor/Assets/css/kit.css',
    ];
    private const GLOBAL_JS = ['plugins/Harbor/Assets/js/kit.js'];
    private const GLOBAL_GZIP_BUDGET = 20_000;

    public function testGlobalAssetsAndGzipBudget(): void
    {
        (new Plugin($this->container))->initialize();

        $hook = $this->container['hook'];
        $css = array_column($hook->getListeners('template:layout:css'), 'template');
        $js = array_column($hook->getListeners('template:layout:js'), 'template');
        $this->assertSame(self::GLOBAL_CSS, $css);
        $this->assertSame(self::GLOBAL_JS, $js);

        $bytes = 0;
        foreach ([...$css, ...$js] as $path) {
            $contents = file_get_contents(dirname(__DIR__) . '/' . substr($path, strlen('plugins/Harbor/')));
            $bytes += strlen(gzencode($contents, 9));
        }

        $this->assertSame(20_000, self::GLOBAL_GZIP_BUDGET);
        $this->assertLessThanOrEqual(self::GLOBAL_GZIP_BUDGET, $bytes);
    }

    /** @dataProvider routes */
    public function testLayoutAssetListeners(string $controller, array $stylesheets, array $scripts): void
    {
        $router = $this->createMock(Router::class);
        $router->method('getController')->willReturn($controller);
        $router->method('getPlugin')->willReturn('');
        $this->container['router'] = $router;

        (new Plugin($this->container))->initialize();
        $this->dispatcher->dispatch(new Event(), 'app.bootstrap');

        $hook = $this->container['hook'];
        $this->assertSame($stylesheets, array_column($hook->getListeners('template:layout:css'), 'template'));
        $this->assertSame($scripts, array_column($hook->getListeners('template:layout:js'), 'template'));
    }

    public static function routes(): array
    {
        return [
            'board' => [
                'BoardViewController',
                [...self::GLOBAL_CSS, 'plugins/Harbor/Assets/css/kit-board.css'],
                [...self::GLOBAL_JS, 'plugins/Harbor/Assets/js/kit-board.js'],
            ],
            'login' => ['AuthController', [...self::GLOBAL_CSS, 'plugins/Harbor/Assets/css/login.css'], self::GLOBAL_JS],
            'task' => ['TaskViewController', self::GLOBAL_CSS, self::GLOBAL_JS],
        ];
    }
}
