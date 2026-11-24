<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Http\Router;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;
use Symfony\Contracts\EventDispatcher\Event;

class AssetContractTest extends Base
{
    /** @dataProvider routes */
    public function testLayoutAssetListeners(string $controller, array $stylesheets): void
    {
        $router = $this->createMock(Router::class);
        $router->method('getController')->willReturn($controller);
        $this->container['router'] = $router;

        (new Plugin($this->container))->initialize();
        $this->dispatcher->dispatch(new Event(), 'app.bootstrap');

        $hook = $this->container['hook'];
        $this->assertSame($stylesheets, array_column($hook->getListeners('template:layout:css'), 'template'));
        $this->assertSame([
            'plugins/Harbor/Assets/js/kit.js',
        ], array_column($hook->getListeners('template:layout:js'), 'template'));
    }

    public static function routes(): array
    {
        $globalStylesheets = [
            'plugins/Harbor/Assets/css/tokens.css',
            'plugins/Harbor/Assets/css/kit.css',
        ];

        return [
            'board' => ['BoardViewController', $globalStylesheets],
            'login' => ['AuthController', [...$globalStylesheets, 'plugins/Harbor/Assets/css/login.css']],
        ];
    }
}
