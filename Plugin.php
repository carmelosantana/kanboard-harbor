<?php

namespace Kanboard\Plugin\Harbor;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\AuthenticationManager;
use Kanboard\Model\UserModel;
use Kanboard\Plugin\Harbor\Action\ActionRegistry;
use Kanboard\Plugin\Harbor\Assets\ModuleLoader;
use Kanboard\Plugin\Harbor\Controller\SubtaskStatusController;
use Kanboard\Plugin\Harbor\Helper\KitHelper;
use Kanboard\Plugin\Harbor\Theme\TemplateChain;
use WeakMap;

/**
 * Harbor Plugin (successor to ShadcnTheme)
 *
 * The suite's flagship theme and UI kit: Newburgh and Shadcn presets in light and
 * dark, a server-rendered theme with no flash, the kit components and the
 * kit:actions registry.
 *
 * @package  Kanboard\Plugin\Harbor
 * @author   Carmelo Santana <carmelo@carmelosantana.com>
 * @license  MIT License
 * @link     https://github.com/carmelosantana/kanboard-harbor
 */
class Plugin extends Base
{
    private const LEGACY_THEME_KEY = 'shadcn_theme_mode';

    /** @var WeakMap<\PicoDb\Database, bool>|null */
    private static ?WeakMap $shadcnHistory = null;

    /**
     * Initialize the plugin
     *
     * @access public
     */
    public function initialize()
    {
        $this->container['harborActionRegistry'] = static fn ($container) => new ActionRegistry($container);
        $this->helper->register('kit', KitHelper::class);
        $this->hook->on('kit:actions', $this->subtaskActions(...));
        $this->hook->on('kit:modules', static fn (string $controller, ?string $plugin): array =>
            $controller === 'BoardViewController' && $plugin === null ? ['board'] : []
        );
        $this->hook->on('kit:modules', static fn (string $controller, ?string $plugin): array =>
            $controller === 'HarborStyleGuideController' && $plugin === 'Harbor'
                ? ['board', 'activity', 'float', 'chat', 'charts', 'ops', 'nav']
                : []
        );

        $this->migrateThemePreference();
        // At normal plugin load the session has yet to open.
        $this->on('app.bootstrap', $this->migrateThemePreference(...));
        $this->on(AuthenticationManager::EVENT_SUCCESS, $this->migrateThemePreference(...));
        
        // Hook into CSS and JS assets
        $this->hookAssets();
        
        // Hook into template overrides
        $this->hookTemplates();
        $this->hook->on('template:config:sidebar', ['template' => 'Harbor:config/sidebar']);
        $this->template->hook->attach('template:subtask:table:header:before-timetracking', 'Harbor:subtask/row_actions', ['header' => true]);
        $this->template->hook->attachCallable(
            'template:subtask:table:rows',
            'Harbor:subtask/row_actions',
            fn (array $subtask): array => [
                'projectId' => $this->memoryCache->proxy($this->taskFinderModel, 'getProjectId', $subtask['task_id']),
                'startAllowed' => !$this->memoryCache->proxy(
                    $this->subtaskStatusModel,
                    'hasSubtaskInProgress',
                    $this->userSession->getId()
                ),
            ]
        );
        
        // Add custom routes
        $this->addRoutes();
    }

    private function subtaskActions(): array
    {
        $actions = [];
        $can = fn (array $entity, array $user): bool => SubtaskStatusController::canSet(
            $this->container,
            (int) ($entity['project_id'] ?? 0),
            (int) ($user['id'] ?? 0)
        );

        foreach ([
            ['done', 'Mark done', 'd', 'status != done', 'done'],
            ['start', 'Start working', 's', 'status == todo and start_allowed == 1', 'doing'],
            ['todo', 'Back to todo', 't', 'status != todo', 'todo'],
        ] as [$id, $label, $key, $when, $status]) {
            $actions[] = [
                'id' => 'subtask.' . $id,
                'label' => t($label),
                'slot' => 'subtask-row',
                'key' => $key,
                'when' => $when,
                'states_from' => SubtaskStatusController::STATUSES,
                'route' => ['SubtaskStatusController', 'set', 'plugin' => 'Harbor', 'status' => $status],
                'risk' => 'reversible',
                'stepUp' => false,
                'can' => $can,
            ];
        }

        return $actions;
    }

    private function migrateThemePreference(): void
    {
        $userId = $this->userSession->getId();
        $marker = 'harbor_migrated_theme';
        if ($userId <= 0 || ($_SESSION[$marker] ?? null) === $userId) {
            return;
        }
        if ($this->userMetadataModel->exists($userId, $marker)) {
            $_SESSION[$marker] = $userId;
            return;
        }

        $legacyTheme = $this->userMetadataModel->get($userId, self::LEGACY_THEME_KEY, null);
        if ($legacyTheme === null && $this->hasShadcnHistory()
            && !$this->userMetadataModel->exists($userId, self::LEGACY_THEME_KEY)) {
            $legacyTheme = 'dark';
        }
        $theme = $legacyTheme === 'system' ? 'auto' : $legacyTheme;
        if (in_array($theme, ['dark', 'auto'], true)) {
            // Check the stored theme in the write itself in case another session changed it.
            $this->db->table(UserModel::TABLE)
                ->eq('id', $userId)
                ->eq('theme', 'light')
                ->update(['theme' => $theme]);

            $postAuthValidated = $this->userSession->isPostAuthenticationValidated();
            $this->userSession->refresh($userId);
            if ($postAuthValidated) {
                $this->userSession->setPostAuthenticationAsValidated();
            }
        }

        if ($this->userMetadataModel->save($userId, [$marker => '1'])) {
            $_SESSION[$marker] = $userId;
        }
    }

    private function hasShadcnHistory(): bool
    {
        self::$shadcnHistory ??= new WeakMap();
        return self::$shadcnHistory[$this->db] ??= $this->detectShadcnHistory();
    }

    private function detectShadcnHistory(): bool
    {
        foreach (array_keys($this->configModel->getAll()) as $option) {
            if (str_starts_with($option, 'shadcn_')) {
                return true;
            }
        }

        return $this->db->table('user_has_metadata')->eq('name', self::LEGACY_THEME_KEY)->exists();
    }

    /**
     * Hook CSS and JavaScript assets
     *
     * @return void
     */
    private function hookAssets()
    {
        // Load palette tokens before component styles.
        $this->hook->on('template:layout:css', [
            'template' => 'plugins/Harbor/Assets/css/tokens.css'
        ]);
        
        $this->hook->on('template:layout:css', [
            'template' => 'plugins/Harbor/Assets/css/kit.css'
        ]);

        $this->hook->on('template:layout:js', [
            'template' => 'plugins/Harbor/Assets/js/kit.js',
        ]);

        // Core's asset hook takes static paths. Register them after the router dispatches.
        $this->on('app.bootstrap', function (): void {
            $controller = $this->router->getController();
            $plugin = $this->router->getPlugin();
            (new ModuleLoader($this->hook, $this->logger))->register($controller, $plugin === '' ? null : $plugin);

            if (in_array($controller, ['AuthController', 'TwoFactorController'], true)) {
                $this->hook->on('template:layout:css', [
                    'template' => 'plugins/Harbor/Assets/css/login.css',
                ]);
            }
            if ($controller === 'TwoFactorController' && $this->router->getAction() === 'code') {
                $this->hook->on('template:layout:js', [
                    'template' => 'plugins/Harbor/Assets/js/login-code.js',
                ]);
            }
        });
    }

    /**
     * Hook template overrides
     *
     * @return void
     */
    private function hookTemplates()
    {
        $template = $this->template;
        TemplateChain::register($template, 'layout', 'Harbor:layout');

        // Place the POST form beside the dropdown, outside the menu cloned by core.
        TemplateChain::register($template, 'header/user_dropdown', 'Harbor:header/theme_toggle');

        $template->setTemplateOverride('auth/index', 'Harbor:auth/index');
        $template->setTemplateOverride('twofactor/check', 'Harbor:twofactor/check');
    }

    /**
     * Add custom routes
     *
     * @return void
     */
    private function addRoutes()
    {
        $this->route->addRoute('harbor/theme/:mode', 'ThemeController', 'set', 'Harbor');
        $this->route->addRoute('harbor/density/:mode', 'DensityController', 'set', 'Harbor');
        $this->route->addRoute('harbor/subtask/:subtask_id/status/:status', 'SubtaskStatusController', 'set', 'Harbor');
    }

    /**
     * Get plugin name
     *
     * @return string
     */
    public function getPluginName(): string
    {
        return 'Harbor';
    }

    /**
     * Get plugin description
     *
     * @return string
     */
    public function getPluginDescription(): string
    {
        return 'The suite\'s flagship theme and UI kit: Newburgh and Shadcn presets, light and dark, no-flicker loading.';
    }

    /**
     * Get plugin author
     *
     * @return string
     */
    public function getPluginAuthor(): string
    {
        return 'Carmelo Santana';
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function getPluginVersion(): string
    {
        return '2.0.0-beta.2';
    }

    /**
     * Get compatible Kanboard version
     *
     * @return string
     */
    public function getCompatibleVersion(): string
    {
        return '>=1.2.47';
    }

    /**
     * Get plugin homepage
     *
     * @return string
     */
    public function getPluginHomepage(): string
    {
        return 'https://github.com/carmelosantana/kanboard-harbor';
    }

    /**
     * Get plugin license
     *
     * @return string
     */
    public function getPluginLicense(): string
    {
        return 'MIT';
    }
}
