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
 * @brief Laravel View Engine for rendering Smarty templates (.tpl files)
 *
 * Integrates Smarty into Laravel's view system. When Laravel resolves a .tpl file,
 * this engine renders it via Smarty. Nested {include}s are handled by SmartyTemplate.
 */

namespace PKP\core\blade;

use APP\template\TemplateManager;
use Illuminate\Contracts\View\Engine;

class SmartyTemplatingEngine implements Engine
{
    /**
     * Render a Smarty template
     *
     * @param string $path Absolute file path (already resolved by FileViewFinder)
     * @param array $data View data from Laravel
     */
    public function get($path, array $data = []): string
    {
        $templateManager = TemplateManager::getManager();

        // Create template with file: prefix (bypasses Smarty resource resolution)
        // compile_id ensures different themes get separate compiled versions
        $template = $templateManager->createTemplate(
            'file:' . $path,
            $templateManager,
            null, // cache_id - not using Smarty's output caching
            $templateManager->getCompileId($path)
        );

        // Ensure TemplateManager's assigned vars are available to Smarty templates.
        // For top-level fetch(): $data is empty (View::share only helps Blade, not Smarty)
        // For nested includes: $data already has vars, but merge is harmless
        $template->assign(array_merge($templateManager->getTemplateVars(), $data));

        return $template->fetch();
    }
}
