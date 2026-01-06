<?php

/**
 * @file classes/core/blade/SmartyTemplate.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SmartyTemplate
 *
 * @brief Routes Smarty {include} directives through Laravel's view system
 *
 * Intercepts nested template includes and resolves them through Laravel's
 * FileViewFinder, enabling:
 * - Plugin template overrides via View::alias hook
 * - Blade templates to be included from Smarty templates
 * - Unified template resolution for both engines
 */

namespace PKP\core\blade;

use PKP\plugins\Hook;
use Smarty_Internal_Template;

class SmartyTemplate extends Smarty_Internal_Template
{
    /**
     * Override sub-template rendering to route through Laravel's view system
     */
    public function _subTemplateRender(
        $template,
        $cache_id,
        $compile_id,
        $caching,
        $cache_lifetime,
        $data,
        $scope,
        $forceTplCache,
        $uid = null,
        $content_func = null
    ): void {
        // Smarty built-in resources (file:, string:, eval:) bypass Laravel
        if (preg_match('/^(file|string|eval):/', $template)) {
            parent::_subTemplateRender(
                $template, $cache_id, $compile_id, $caching,
                $cache_lifetime, $data, $scope, $forceTplCache, $uid, $content_func
            );
            return;
        }

        // Convert Smarty path to Laravel view name
        $viewName = \APP\template\TemplateManager::getManager()->smartyPathToViewName($template);

        // Fire View::alias hook for plugin overrides
        $aliased = null;
        Hook::call('View::alias', [&$aliased, $viewName]);
        if ($aliased !== null) {
            $viewName = $aliased;
        }

        // Resolve file path through Laravel
        $filePath = app('view.finder')->find($viewName);

        // Route based on resolved file type
        if (str_ends_with($filePath, '.blade')) {
            // Blade: render via Laravel and output
            $templateVars = array_merge($this->getTemplateVars(), $data ?? []);
            echo view($viewName, $templateVars)->render();
        } else {
            // Smarty: use file: prefix with resolved path
            parent::_subTemplateRender(
                'file:' . $filePath, $cache_id, $compile_id, $caching,
                $cache_lifetime, $data, $scope, $forceTplCache, $uid, $content_func
            );
        }
    }
}
