<?php

namespace Kanboard\Plugin\Harbor;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\AuthenticationManager;
use Kanboard\Core\Template;
use Kanboard\Model\UserModel;
use Kanboard\Plugin\Harbor\Helper\KitHelper;
use Kanboard\Plugin\Harbor\Theme\LayoutChain;
use WeakMap;

/**
 * Harbor Plugin (successor to ShadcnTheme)
 *
 * A modern Kanboard theme inspired by shadcn/ui design principles.
 * Features light/dark mode toggle with system preference detection,
 * beautiful typography, refined color palette, and improved UX.
 *
 * @package  Kanboard\Plugin\Harbor
 * @author   Carmelo Santana <carmelo@carmelosantana.com>
 * @license  MIT License
 * @link     https://github.com/carmelosantana/kanboard-harbor
 */
class Plugin extends Base
{
    /** @var WeakMap<Template, string>|null */
    private static ?WeakMap $previousDropdowns = null;

    /**
     * Initialize the plugin
     *
     * @access public
     */
    public function initialize()
    {
        $this->helper->register('kit', KitHelper::class);

        $this->migrateThemePreference();
        // At normal plugin load the session has yet to open.
        $this->on('app.bootstrap', $this->migrateThemePreference(...));
        $this->on(AuthenticationManager::EVENT_SUCCESS, $this->migrateThemePreference(...));
        
        // Hook into CSS and JS assets
        $this->hookAssets();
        
        // Hook into template overrides
        $this->hookTemplates();
        
        // Add custom routes
        $this->addRoutes();
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

        $legacyTheme = $this->userMetadataModel->get($userId, 'shadcn_theme_mode');
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
        LayoutChain::register($template, 'Harbor:layout');

        // Place the POST form beside the dropdown, outside the menu cloned by core.
        self::$previousDropdowns ??= new WeakMap();
        if (!isset(self::$previousDropdowns[$template])) {
            self::$previousDropdowns[$template] = $template->getTemplateFile('header/user_dropdown');
            $template->setTemplateOverride('header/user_dropdown', 'Harbor:header/theme_toggle');
        }

        $template->setTemplateOverride('auth/index', 'Harbor:auth/index');
        $template->setTemplateOverride('twofactor/check', 'Harbor:twofactor/check');
    }

    public static function previousUserDropdown(Template $template): string
    {
        return self::$previousDropdowns[$template];
    }

    /**
     * Add custom routes for the theme API
     *
     * @return void
     */
    private function addRoutes()
    {
        $this->route->addRoute('harbor/theme/:mode', 'ThemeController', 'set', 'Harbor');
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
        return '2.0.0-dev';
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
