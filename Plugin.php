<?php

namespace Kanboard\Plugin\Harbor;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\AuthenticationManager;
use Kanboard\Model\UserModel;
use Kanboard\Plugin\Harbor\Theme\LayoutChain;

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
    /**
     * Initialize the plugin
     *
     * @access public
     */
    public function initialize()
    {
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
        if ($userId <= 0 || $this->userMetadataModel->exists($userId, $marker)) {
            return;
        }

        $legacyTheme = $this->userMetadataModel->get($userId, 'shadcn_theme_mode');
        $theme = $legacyTheme === 'system' ? 'auto' : $legacyTheme;
        if (in_array($theme, ['dark', 'auto'], true)) {
            // Check the stored theme in the write itself in case another session changed it.
            $saved = $this->db->table(UserModel::TABLE)
                ->eq('id', $userId)
                ->eq('theme', 'light')
                ->update(['theme' => $theme]);
            if (!$saved) {
                return;
            }

            $postAuthValidated = $this->userSession->isPostAuthenticationValidated();
            $this->userSession->refresh($userId);
            if ($postAuthValidated) {
                $this->userSession->setPostAuthenticationAsValidated();
            }
        }

        $this->userMetadataModel->save($userId, [$marker => '1']);
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

        // Login card styles (injected on every page; rules are scoped to .form-login)
        $this->hook->on('template:layout:css', [
            'template' => 'plugins/Harbor/Assets/css/login.css'
        ]);
    }

    /**
     * Hook template overrides
     *
     * @return void
     */
    private function hookTemplates()
    {
        LayoutChain::register($this->template, 'Harbor:layout');

        // Place the POST form beside the stock dropdown, which clones and removes its menu.
        $this->template->setTemplateOverride('header/user_dropdown', 'Harbor:header/theme_toggle');

        // Login card header (Steward's logo or site name, else the product name, + subtitle)
        $this->hook->on('template:auth:login-form:before', [
            'template' => 'Harbor:auth/login_header'
        ]);
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
