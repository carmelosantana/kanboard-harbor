<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Controller\Runner;
use Kanboard\Core\Http\Response;
use Kanboard\Core\Session\SessionManager;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;
use Symfony\Contracts\EventDispatcher\Event;

class StyleGuideTest extends Base
{
    private const MODULES = ['board', 'activity', 'float', 'chat', 'charts', 'ops', 'nav'];

    public function testStyleGuideRequestsEveryModuleThroughTheHook(): void
    {
        $this->routeStyleGuide();
        $modules = [];
        $this->container['hook']->merge('kit:modules', $modules, [
            $this->container['router']->getController(),
            $this->container['router']->getPlugin(),
        ]);
        $this->assertSame(self::MODULES, $modules);

        $this->container['dispatcher']->dispatch(new Event(), 'app.bootstrap');
        $hook = $this->container['hook'];
        foreach (['css' => self::MODULES, 'js' => ['float', 'chat', 'charts', 'ops', 'nav']] as $type => $expected) {
            $paths = array_column($hook->getListeners('template:layout:' . $type), 'template');
            foreach ($expected as $module) {
                $path = "plugins/Harbor/Assets/$type/kit-$module.$type";
                $this->assertSame(1, count(array_keys($paths, $path, true)), $path);
            }
        }
    }

    public function testStyleGuideRendersEverySurfaceSlotInAnExampleSection(): void
    {
        $this->routeStyleGuide();
        $response = $this->createMock(Response::class);
        $this->container['response'] = $response;
        $response->expects($this->once())->method('html')->with($this->callback(function (string $html): bool {
            $document = new DOMDocument();
            $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            foreach ([
                'activity-row', 'heatmap', 'stage', 'float', 'icon-button', 'fab',
                'chat-thread', 'chat-message', 'tool-chip', 'composer', 'stat-tile',
                'sparkline', 'line-chart', 'chart-legend', 'chart-table', 'usage-bar',
                'status', 'data-table', 'log-viewer', 'step-list', 'step',
                'approval-card', 'typed-confirm', 'service-tile', 'breadcrumbs',
                'date-range', 'skeleton', 'avatar', 'copy-button', 'kbd',
                'shortcut-sheet', 'visually-hidden',
            ] as $slot) {
                $this->assertGreaterThan(0, $xpath->query("//section//*[@data-slot='$slot']")->length, $slot);
            }
            foreach (['self', 'other'] as $from) {
                $this->assertGreaterThan(0, $xpath->query(
                    "//section//*[@data-slot='chat-thread']//*[@data-slot='chat-message'][@data-from='$from']"
                )->length, "chat-message from $from");
            }

            return true;
        }));

        (new Runner($this->container))->execute();
    }

    private function routeStyleGuide(): void
    {
        (new Plugin($this->container))->initialize();
        $this->container['userSession']->initialize($this->container['userModel']->getById(1));
        $this->container['sessionManager'] = $this->createMock(SessionManager::class);
        $this->container['request']->setParams([
            'controller' => 'HarborStyleGuideController',
            'action' => 'show',
            'plugin' => 'Harbor',
        ]);
        $this->container['router']->dispatch();
    }
}
