<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Http\Request;
use Kanboard\Core\Http\Response;
use Kanboard\Model\UserMetadataModel;
use Kanboard\Plugin\Harbor\Controller\ThemeController;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;
use Symfony\Contracts\EventDispatcher\Event;

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

    public function testHeaderChainsThePreviouslyRegisteredDropdown(): void
    {
        $this->container['userSession']->initialize($this->container['userModel']->getById(1));
        $template = $this->container['template'];
        $template->setTemplateOverride('header/user_dropdown', 'Harbor:../Test/fixtures/user-dropdown');

        (new Plugin($this->container))->initialize();
        (new Plugin($this->container))->initialize();
        $html = $template->render('header/user_dropdown', ['dropdownLabel' => 'Previous dropdown']);

        $this->assertSame(1, substr_count($html, '<div id="fake-user-dropdown">Previous dropdown</div>'));
        $this->assertSame(1, substr_count($html, 'class="harbor-theme-toggle"'));
    }

    public function testLegacySystemThemeMigratesOnceAfterTheSessionOpens(): void
    {
        $metadata = $this->container['userMetadataModel'];
        $users = $this->container['userModel'];
        $session = $this->container['userSession'];
        $metadata->save(1, ['shadcn_theme_mode' => 'system']);
        (new Plugin($this->container))->initialize();

        $this->assertFalse($metadata->exists(1, 'harbor_migrated_theme'));
        $session->initialize($users->getById(1));
        $session->setPostAuthenticationAsValidated();
        $this->dispatcher->dispatch(new Event(), 'app.bootstrap');

        $this->assertSame('auto', $users->getById(1)['theme']);
        $this->assertSame('auto', $session->getTheme());
        $this->assertSame('1', (string) $metadata->get(1, 'harbor_migrated_theme'));
        $this->assertTrue($session->isPostAuthenticationValidated());

        $users->update(['id' => 1, 'theme' => 'light']);
        $metadata->save(1, ['shadcn_theme_mode' => 'dark']);
        $this->dispatcher->dispatch(new Event(), 'app.bootstrap');

        $this->assertSame('light', $users->getById(1)['theme']);
        $this->assertSame('light', $session->getTheme());
    }

    public function testMigrationPreservesDarkEvenWhenTheSessionStillSaysLight(): void
    {
        $metadata = $this->container['userMetadataModel'];
        $users = $this->container['userModel'];
        $oldSession = $users->getById(1);
        $users->update(['id' => 1, 'theme' => 'dark']);
        $this->container['userSession']->initialize($oldSession);
        $metadata->save(1, ['shadcn_theme_mode' => 'system']);

        (new Plugin($this->container))->initialize();

        $this->assertSame('dark', $users->getById(1)['theme']);
        $this->assertSame('1', (string) $metadata->get(1, 'harbor_migrated_theme'));
    }

    public function testMigrationMarksAnAccountWithoutLegacyMetadata(): void
    {
        $users = $this->container['userModel'];
        (new Plugin($this->container))->initialize();
        $this->container['userSession']->initialize($users->getById(1));
        $this->dispatcher->dispatch(new Event(), 'auth.success');

        $this->assertSame('light', $users->getById(1)['theme']);
        $this->assertSame('1', (string) $this->container['userMetadataModel']->get(1, 'harbor_migrated_theme'));
    }

    public function testMigrationSkipsMetadataQueriesOnTheSecondBootstrap(): void
    {
        $metadata = $this->createMock(UserMetadataModel::class);
        $metadata->expects($this->once())->method('exists')->with(1, 'harbor_migrated_theme')->willReturn(true);
        $metadata->expects($this->never())->method('get');
        $metadata->expects($this->never())->method('save');
        unset($this->container['userMetadataModel']);
        $this->container['userMetadataModel'] = $metadata;

        (new Plugin($this->container))->initialize();
        $this->container['userSession']->initialize($this->container['userModel']->getById(1));
        $this->dispatcher->dispatch(new Event(), 'app.bootstrap');
        $this->dispatcher->dispatch(new Event(), 'app.bootstrap');
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
