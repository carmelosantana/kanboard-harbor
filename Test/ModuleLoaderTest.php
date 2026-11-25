<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Plugin\Hook;
use Kanboard\Plugin\Harbor\Assets\ModuleLoader;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;
use Psr\Log\LoggerInterface;

class ModuleLoaderTest extends Base
{
    private ?string $assetDirectory = null;

    protected function tearDown(): void
    {
        if ($this->assetDirectory !== null) {
            foreach (glob($this->assetDirectory . '/*/*') as $path) {
                unlink($path);
            }
            rmdir($this->assetDirectory . '/css');
            rmdir($this->assetDirectory . '/js');
            rmdir($this->assetDirectory);
        }
        parent::tearDown();
    }

    /** @dataProvider routes */
    public function testRegistersChartsOnlyOnTheRequestedRoute(string $controller, ?string $plugin, bool $expected): void
    {
        $hook = new Hook();
        $hook->on('kit:modules', static fn (string $controller, ?string $plugin): array =>
            $controller === 'TaskViewController' && $plugin === null ? ['charts'] : []
        );

        $this->loader($hook)->register($controller, $plugin);

        $this->assertAssets($hook, $expected ? ['charts'] : [], $expected ? ['charts'] : []);
    }

    public static function routes(): array
    {
        return [
            'task' => ['TaskViewController', null, true],
            'dashboard' => ['DashboardController', null, false],
            'plugin' => ['TaskViewController', 'Example', false],
        ];
    }

    public function testRegistersEachAssetOnceAcrossListeners(): void
    {
        $hook = new Hook();
        $hook->on('kit:modules', static fn (): array => ['charts']);
        $hook->on('kit:modules', static fn (): array => ['charts']);

        $this->loader($hook)->register('TaskViewController', null);

        $this->assertAssets($hook, ['charts'], ['charts']);
    }

    public function testSkipsAndLogsUnknownModules(): void
    {
        $hook = new Hook();
        $hook->on('kit:modules', static fn (): array => ['unknown', 'charts']);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            $this->stringContains('kit:modules'),
            ['module' => 'unknown']
        );

        $this->loader($hook, $logger)->register('TaskViewController', null);

        $this->assertAssets($hook, ['charts'], ['charts']);
    }

    public function testRegistersStylesheetForModuleWithoutJavaScript(): void
    {
        $hook = new Hook();
        $hook->on('kit:modules', static fn (): array => ['board']);

        $this->loader($hook)->register('BoardViewController', null);

        $this->assertAssets($hook, ['board'], []);
    }

    public function testHarborRequestsBoardForTheCoreBoardController(): void
    {
        (new Plugin($this->container))->initialize();
        $modules = [];

        $this->container['hook']->merge('kit:modules', $modules, ['BoardViewController', null]);

        $this->assertSame(['board'], $modules);
    }

    private function loader(Hook $hook, ?LoggerInterface $logger = null): ModuleLoader
    {
        $this->assetDirectory = sys_get_temp_dir() . '/harbor-module-assets-' . bin2hex(random_bytes(8));
        mkdir($this->assetDirectory . '/css', 0700, true);
        mkdir($this->assetDirectory . '/js', 0700);
        touch($this->assetDirectory . '/css/kit-charts.css');
        touch($this->assetDirectory . '/js/kit-charts.js');
        touch($this->assetDirectory . '/css/kit-board.css');
        touch($this->assetDirectory . '/css/kit-unknown.css');

        return new ModuleLoader($hook, $logger ?? $this->container['logger'], $this->assetDirectory);
    }

    private function assertAssets(Hook $hook, array $css, array $js): void
    {
        $this->assertSame(
            array_map(static fn (string $name): string => "plugins/Harbor/Assets/css/kit-$name.css", $css),
            array_column($hook->getListeners('template:layout:css'), 'template')
        );
        $this->assertSame(
            array_map(static fn (string $name): string => "plugins/Harbor/Assets/js/kit-$name.js", $js),
            array_column($hook->getListeners('template:layout:js'), 'template')
        );
    }
}
