<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Http\Request;
use Kanboard\Core\Http\Response;
use Kanboard\Plugin\Harbor\Controller\ThemeController;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;

class ThemeControllerTest extends Base
{
    public function testInvalidModeReturnsBadRequestWithoutWriting(): void
    {
        [$controller, $response] = $this->prepareRequest('system');
        $response->expects($this->once())->method('json')->with(['error' => 'Invalid theme mode'], 400);

        $controller->set();

        $this->assertSame('light', $this->container['userModel']->getById(1)['theme']);
    }

    public function testMissingFormTokenIsForbiddenAndDoesNotWrite(): void
    {
        [$controller, $response] = $this->prepareRequest('dark', csrf: false);
        $response->expects($this->never())->method('json');
        $this->expectException(AccessForbiddenException::class);

        try {
            $controller->set();
        } finally {
            $this->assertSame('light', $this->container['userModel']->getById(1)['theme']);
        }
    }

    public function testGetIsRejectedEvenWithAValidToken(): void
    {
        [$controller, $response] = $this->prepareRequest('dark', method: 'GET');
        $response->expects($this->once())->method('withHeader')->with('Allow', 'POST')->willReturnSelf();
        $response->expects($this->once())->method('status')->with(405);

        $controller->set();

        $this->assertSame('light', $this->container['userModel']->getById(1)['theme']);
    }

    public function testPostWritesCoreThemeAndRefreshesTheAuthenticatedSession(): void
    {
        [$controller, $response] = $this->prepareRequest('auto');
        $this->container['userSession']->setPostAuthenticationAsValidated();
        $response->expects($this->once())->method('json')->with(['success' => true, 'mode' => 'auto']);

        $controller->set();

        $this->assertSame('auto', $this->container['userModel']->getById(1)['theme']);
        $this->assertSame('auto', $this->container['userSession']->getTheme());
        $this->assertTrue($this->container['userSession']->isPostAuthenticationValidated());
    }

    public function testFormRedirectsBackToTheCurrentPage(): void
    {
        $returnTo = '/kanboard/?controller=BoardViewController&action=show&project_id=7';
        [$controller, $response] = $this->prepareRequest('dark', ajax: false, returnTo: $returnTo);
        $response->expects($this->once())->method('redirect')->with($returnTo);

        $controller->set();

        $this->assertSame('dark', $this->container['userSession']->getTheme());
    }

    public function testFormRejectsExternalRedirects(): void
    {
        [$controller, $response] = $this->prepareRequest('dark', ajax: false, returnTo: '/\\outside.example');
        $response->expects($this->once())->method('redirect')->with('/kanboard/?controller=DashboardController&action=show');

        $controller->set();
    }

    public function testCleanRouteDispatchesToTheHarborController(): void
    {
        $this->container['route']->enable();
        (new Plugin($this->container))->initialize();

        $this->assertSame(
            ['controller' => 'ThemeController', 'action' => 'set', 'plugin' => 'Harbor'],
            $this->container['route']->findRoute('harbor/theme/auto')
        );
        $this->assertSame('auto', $this->container['request']->getStringParam('mode'));
    }

    public function testHeaderOffersEachCoreThemeViaPost(): void
    {
        $this->container['userSession']->initialize($this->container['userModel']->getById(1));
        (new Plugin($this->container))->initialize();
        $html = $this->container['template']->render('header/user_dropdown');

        $this->assertStringContainsString('<form method="post"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        foreach (['light', 'dark', 'auto'] as $mode) {
            $this->assertStringContainsString('action=set&amp;plugin=Harbor&amp;mode=' . $mode, $html);
        }
        $this->assertStringContainsString('dropdown-menu-link-icon', $html);
        $this->assertStringNotContainsString('<style', $html);
    }

    private function prepareRequest(
        string $mode,
        bool $csrf = true,
        string $method = 'POST',
        bool $ajax = true,
        string $returnTo = ''
    ): array {
        $this->container['userSession']->initialize($this->container['userModel']->getById(1));
        $post = ['return_to' => $returnTo];
        if ($csrf) {
            $post['csrf_token'] = $this->container['token']->getCSRFToken();
        }
        unset($this->container['request']);
        $this->container['request'] = new Request($this->container, [
            'REQUEST_METHOD' => $method,
            'PHP_SELF' => '/kanboard/index.php',
            'HTTP_X_REQUESTED_WITH' => $ajax ? 'XMLHttpRequest' : '',
        ], ['mode' => $mode], $post);

        $response = $this->createMock(Response::class);
        unset($this->container['response']);
        $this->container['response'] = $response;

        return [new ThemeController($this->container), $response];
    }
}
