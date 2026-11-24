<?php

namespace Kanboard\Plugin\Harbor;

use Kanboard\Core\Plugin\Base;
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
        // Initialize theme preferences
        $this->initializeTheme();
        
        // Hook into CSS and JS assets
        $this->hookAssets();
        
        // Hook into template overrides
        $this->hookTemplates();
        
        // Hook into user preferences
        $this->hookUserPreferences();
        
        // Add custom routes
        $this->addRoutes();
    }

    /**
     * Initialize theme configuration
     *
     * @return void
     */
    private function initializeTheme()
    {
        // Check if user has theme preference stored
        $currentUserId = $this->userSession->getId();
        if ($currentUserId) {
            $themePreference = $this->userMetadataModel->get(
                $currentUserId,
                'shadcn_theme_mode',
                'dark'
            );

            // Set theme mode in session for JavaScript access
            $_SESSION['shadcn_theme_mode'] = $themePreference;
        } else {
            $_SESSION['shadcn_theme_mode'] = 'dark';
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

        // Login card styles (injected on every page; rules are scoped to .form-login)
        $this->hook->on('template:layout:css', [
            'template' => 'plugins/Harbor/Assets/css/login.css'
        ]);

        // Add theme switching JavaScript
        $this->hook->on('template:layout:js', [
            'template' => 'plugins/Harbor/Assets/js/theme-switcher.js'
        ]);
        
        $this->hook->on('template:layout:js', [
            'template' => 'plugins/Harbor/Assets/js/enhancements.js'
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

        // Add theme toggle to page header dropdown
        $this->hook->on('template:header:dropdown', [
            'template' => 'Harbor:header/theme_toggle'
        ]);

        // Login card header (Steward's logo or site name, else the product name, + subtitle)
        $this->hook->on('template:auth:login-form:before', [
            'template' => 'Harbor:auth/login_header'
        ]);
    }

    /**
     * Hook user preference handling
     *
     * @return void
     */
    private function hookUserPreferences()
    {
        // Add theme preference to user metadata when changed
        $this->hook->on('model:user-metadata:save', function($userId, $key, $value) {
            if ($key === 'shadcn_theme_mode') {
                // Validate theme mode
                if (!in_array($value, ['light', 'dark', 'system'], true)) {
                    $value = 'system';
                }
                return $value;
            }
            return null;
        });
    }

    /**
     * Add custom routes for the theme API
     *
     * @return void
     */
    private function addRoutes()
    {
        $this->route->addRoute('harbor/theme/:mode', 'Harbor:ThemeController', 'setTheme');
        $this->route->addRoute('harbor/theme', 'Harbor:ThemeController', 'getTheme');
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
