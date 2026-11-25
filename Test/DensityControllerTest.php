<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Http\Request;
use Kanboard\Core\Http\Response;
use Kanboard\Plugin\Harbor\Controller\DensityController;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;

class DensityControllerTest extends Base
{
    public function testPostSavesCompactForTheActingUserAndReturnsToThePage(): void
    {
        $returnTo = '/kanboard/?controller=BoardViewController&action=show&project_id=7';
        [$controller, $response] = $this->prepareRequest('compact', returnTo: $returnTo);
        $otherUser = $this->container['userModel']->create(['username' => 'density-other']);
        $metadata = $this->container['userMetadataModel'];
        $metadata->save($otherUser, ['harbor_density' => 'comfortable']);
        $response->expects($this->once())->method('redirect')->with($returnTo);

        $controller->set();

        $this->assertSame('compact', $metadata->get(1, 'harbor_density'));
        $this->assertSame('comfortable', $metadata->get($otherUser, 'harbor_density'));
    }

    public function testPostSavesComfortableAndFallsBackToDashboardForAnExternalReturnUrl(): void
    {
        [$controller, $response] = $this->prepareRequest('comfortable', returnTo: '//outside.example');
        $metadata = $this->container['userMetadataModel'];
        $metadata->save(1, ['harbor_density' => 'compact']);
        $response->expects($this->once())->method('redirect')->with('/kanboard/?controller=DashboardController&action=show');

        $controller->set();

        $this->assertSame('comfortable', $metadata->get(1, 'harbor_density'));
    }

    public function testInvalidModeReturnsBadRequestWithoutWriting(): void
    {
        [$controller, $response] = $this->prepareRequest('dense');
        $response->expects($this->once())->method('json')->with(['error' => 'Invalid density mode'], 400);

        $controller->set();

        $this->assertFalse($this->container['userMetadataModel']->exists(1, 'harbor_density'));
    }

    public function testMissingTokenIsForbiddenWithoutWriting(): void
    {
        [$controller] = $this->prepareRequest('compact', csrf: false);
        $this->expectException(AccessForbiddenException::class);

        try {
            $controller->set();
        } finally {
            $this->assertFalse($this->container['userMetadataModel']->exists(1, 'harbor_density'));
        }
    }

    public function testGuestCannotSaveEvenWithAValidToken(): void
    {
        [$controller] = $this->prepareRequest('compact', loggedIn: false);
        $this->expectException(AccessForbiddenException::class);

        $controller->set();
    }

    public function testGetIsRejectedEvenWithAValidToken(): void
    {
        [$controller, $response] = $this->prepareRequest('compact', method: 'GET');
        $response->expects($this->once())->method('withHeader')->with('Allow', 'POST')->willReturnSelf();
        $response->expects($this->once())->method('status')->with(405);

        $controller->set();

        $this->assertFalse($this->container['userMetadataModel']->exists(1, 'harbor_density'));
    }

    public function testCleanRouteResolvesToTheDensityController(): void
    {
        $this->container['route']->enable();
        (new Plugin($this->container))->initialize();

        $this->assertSame(
            ['controller' => 'DensityController', 'action' => 'set', 'plugin' => 'Harbor'],
            $this->container['route']->findRoute('harbor/density/compact')
        );
        $this->assertSame('compact', $this->container['request']->getStringParam('mode'));
    }

    private function prepareRequest(
        string $mode,
        bool $csrf = true,
        string $method = 'POST',
        string $returnTo = '',
        bool $loggedIn = true
    ): array {
        if ($loggedIn) {
            $this->container['userSession']->initialize($this->container['userModel']->getById(1));
        }
        $post = ['return_to' => $returnTo];
        if ($csrf) {
            $post['csrf_token'] = $this->container['token']->getCSRFToken();
        }
        unset($this->container['request']);
        $this->container['request'] = new Request($this->container, [
            'REQUEST_METHOD' => $method,
            'PHP_SELF' => '/kanboard/index.php',
        ], ['mode' => $mode], $post);

        $response = $this->createMock(Response::class);
        unset($this->container['response']);
        $this->container['response'] = $response;

        return [new DensityController($this->container), $response];
    }
}
