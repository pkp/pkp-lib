<?php

/**
 * @file classes/core/blade/FileViewFinder.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileViewFinder
 *
 * @brief Custom Laravel View Finder with unified plugin template override support
 *
 * This class extends Laravel's FileViewFinder to enable a hook-based template override
 * system, allowing plugins to override core templates with either Blade or Smarty
 * templates for backward compatibility during the migration from Smarty to Blade.
 *
 * UNIFIED RESOLUTION ARCHITECTURE:
 * All template resolution (both Blade and Smarty) goes through this FileViewFinder.
 * This includes:
 * - Direct Blade views: view('frontend.pages.article')
 * - Blade @include directives: @include('components.sidebar')
 * - Smarty {include} directives: {include file="components/sidebar.tpl"}
 *   (routed here via SmartyTemplate class)
 *
 * HOOK INTEGRATION:
 * - Hook: TemplateResource::getFilename
 * - Signature: Hook::call('TemplateResource::getFilename', [&$overridePath, $viewName])
 * - Parameters:
 *   - $overridePath (string|null): Reference to override path. Plugins set this to
 *     an absolute file path (.blade or .tpl) to override the template.
 *   - $viewName (string): View name in dot notation (e.g., 'frontend.pages.article')
 *
 * TEMPLATE OVERRIDE FLOW:
 * 1. Laravel/Smarty calls FileViewFinder::find('frontend.pages.article')
 * 2. Hook fires: TemplateResource::getFilename
 * 3. Plugin checks for override and returns absolute file path:
 *    - /path/to/plugin/templates/frontend/pages/article.blade
 *    - /path/to/plugin/templates/frontend/pages/article.tpl
 * 4. If override found: Cache and return the path
 * 5. If no override: Fall back to parent::find() for standard Laravel resolution
 *
 * ENGINE SELECTION:
 * Laravel automatically selects the correct engine based on file extension:
 * - .blade / .blade.php → Blade CompilerEngine
 * - .tpl → SmartyTemplatingEngine
 *
 * The .tpl extension is registered globally in PKPBladeViewServiceProvider::boot().
 *
 * PRIORITY ORDER:
 * Plugin Blade > Plugin Smarty > Core templates (via parent::find())
 *
 * PERFORMANCE:
 * - Results are cached in $this->views[] array
 * - Same view name is resolved only once per request
 * - Hook fires only for uncached views
 *
 * @see PKP\core\blade\SmartyTemplate - Routes Smarty {include} through this finder
 * @see PKP\core\blade\SmartyTemplatingEngine - Renders .tpl files
 * @see PKP\plugins\Plugin::_overridePluginTemplates() - Hook handler in plugins
 * @see PKP\core\PKPBladeViewServiceProvider::boot() - Registers .tpl extension
 */

namespace PKP\core\blade;

use PKP\plugins\Hook;

class FileViewFinder extends \Illuminate\View\FileViewFinder
{
    /**
     * Find the given view in the list of paths.
     *
     * Fires a hook to allow plugins to override the view path
     * before falling back to standard Laravel resolution.
     *
     * @hook TemplateResource::getFilename [[&$overridePath, $name]]
     *
     * @param string $name View name in dot notation (e.g., 'frontend.pages.article')
     * @return string Resolved absolute file path
     *
     * @throws \InvalidArgumentException When view cannot be found
     */
    public function find($name)
    {
        // Check if already cached
        if (isset($this->views[$name])) {
            return $this->views[$name];
        }

        // Initialize override path
        $overridePath = null;

        // Fire hook: allow plugins to provide override
        Hook::call('TemplateResource::getFilename', [&$overridePath, $name]);

        // If plugin provided an override, verify and cache it
        if ($overridePath !== null && $this->files->exists($overridePath)) {
            $this->views[$name] = $overridePath;
            return $overridePath;
        }

        // Fall back to standard Laravel resolution
        // This checks registered paths in order and finds files with registered extensions
        return parent::find($name);
    }
}
