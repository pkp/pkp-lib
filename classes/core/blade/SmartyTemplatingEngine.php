<?php

/**
 * @file classes/core/blade/SmartyTemplatingEngine.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SmartyTemplatingEngine
 *
 * @brief Custom Laravel View Engine for rendering Smarty templates (.tpl files)
 *
 * This engine integrates Smarty templates into Laravel's view system,
 * enabling a unified template resolution architecture where all templates
 * (both top-level and nested) are resolved through Laravel's FileViewFinder.
 *
 * KEY DESIGN PRINCIPLES:
 *
 * 1. UNIFIED RESOLUTION: Uses createTemplate() with file: prefix to bypass
 *    Smarty's PKPTemplateResource for the top-level template. The template
 *    path has already been resolved by Laravel's FileViewFinder (which fired
 *    the TemplateResource::getFilename hook).
 *
 * 2. NESTED INCLUDES: Our custom SmartyTemplate class intercepts {include}
 *    directives and routes them back through Laravel's FileViewFinder,
 *    ensuring consistent resolution and hook firing for nested templates.
 *
 * 3. SINGLE HOOK ARCHITECTURE: By routing all resolution through FileViewFinder,
 *    we eliminate duplicate hook calls that occur when crossing between
 *    Smarty and Blade template systems.
 *
 * 4. VIEW COMPOSERS: Laravel view composers are triggered by SmartyTemplate
 *    for nested includes, allowing data to be attached declaratively.
 *
 * RENDERING FLOW:
 * 1. Laravel's View system calls get($path, $data) with an already-resolved path
 * 2. We use createTemplate() with file: prefix to create a Smarty template directly
 * 3. Template variables from TemplateManager and $data are merged
 * 4. fetch() renders the template; nested {include}s go through SmartyTemplate
 *
 * @see PKP\core\blade\SmartyTemplate
 * @see PKP\core\blade\FileViewFinder
 */

namespace PKP\core\blade;

use APP\template\TemplateManager;
use Illuminate\View\Engines\Engine;

class SmartyTemplatingEngine extends Engine implements \Illuminate\Contracts\View\Engine
{
    /**
     * Render a Smarty template file directly using Smarty's createTemplate
     *
     * @param string $path Absolute file path to the template (already resolved by FileViewFinder)
     * @param array $data View data to pass to the template
     *
     * @return string Rendered template output
     */
    public function get($path, array $data = [])
    {
        $templateManager = TemplateManager::getManager();

        // Get compile_id for proper cache invalidation on theme changes
        // This ensures different themes get different compiled versions
        $compileId = $templateManager->getCompileId($path);

        // Merge existing Smarty template variables with new Blade view data
        // Blade data takes precedence to allow view composers to override values
        $templateVars = array_merge($templateManager->getTemplateVars(), $data);

        // Use createTemplate() with file: prefix to bypass Smarty resource resolution
        // The path has already been resolved by Laravel's FileViewFinder
        // This ensures the template is loaded directly from the file system
        // Parameters: resource, parent, cache_id, compile_id
        $template = $templateManager->createTemplate(
            'file:' . $path,
            $templateManager,
            null,       // cache_id - not using Smarty's output caching
            $compileId  // compile_id - for proper recompilation on theme changes
        );

        // Assign all template variables to the template instance
        foreach ($templateVars as $key => $value) {
            $template->assign($key, $value);
        }

        // Render the template - nested {include}s will be intercepted by SmartyTemplate
        return $template->fetch();
    }
}
