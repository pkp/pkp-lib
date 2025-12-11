<?php

/**
 * @file classes/core/blade/SmartyTemplatingEngine.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SmartyTemplatingEngine
 *
 * @brief Custom Laravel View Engine for rendering Smarty templates (.tpl files)
 *
 * This engine bridges Laravel's Blade view system with Smarty templates,
 * allowing plugins to override Blade templates with Smarty templates for
 * backward compatibility during the migration from Smarty to Blade.
 *
 * NON-BREAKING BACKWARD COMPATIBILITY:
 * This enables plugins to use .tpl files to override core Blade templates
 * when no corresponding .blade file exists in the plugin.
 *
 * Priority order: Plugin Blade > Plugin Smarty > Core Blade
 *
 * Usage:
 * When a .tpl file is returned by FileViewFinder, Laravel's view system
 * automatically uses this engine to render it via PKPTemplateManager.
 */

namespace PKP\core\blade;

use APP\template\TemplateManager;
use Illuminate\View\Engines\Engine;

class SmartyTemplatingEngine extends Engine implements \Illuminate\Contracts\View\Engine
{
    /**
     * Render a Smarty template file using PKPTemplateManager
     *
     * @param string $path Smarty resource path (e.g., "plugins-...:frontend/pages/article.tpl")
     *                     or relative template path for core templates
     * @param array $data View data to pass to the template
     *
     * @return string Rendered template output
     */
    public function get($path, array $data = [])
    {
        $templateManager = TemplateManager::getManager();

        // Transfer Blade view data to Smarty template manager
        // This ensures variables passed to view() are available in Smarty
        foreach ($data as $key => $value) {
            $templateManager->assign($key, $value);
        }

        // Use PKPTemplateManager to render the Smarty template
        // PKPTemplateManager::fetch() handles both:
        // - Smarty resource notation (e.g., "plugins-...:template.tpl")
        // - Relative paths (e.g., "templates/frontend/pages/article.tpl")
        return $templateManager->fetch($path);
    }
}
