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
 * - Plugin template overrides via View::resolveName hook
 * - Blade templates to be included from Smarty templates
 * - Unified template resolution for both engines
 */

namespace PKP\core\blade;

use PKP\plugins\Hook;
use Smarty_Internal_Template;

class SmartyTemplate extends Smarty_Internal_Template
{
    /**
     * Override fetch to route through Laravel's view system
     *
     * This handles cases where Smarty plugins call $smarty->fetch() directly
     * (e.g., FormBuilderVocabulary::_smartyFBVSelect calling $smarty->fetch('form/select.tpl'))
     *
     * Unlike routing through TemplateManager, this preserves variables assigned
     * to this template instance (e.g., $smarty->assign('var', $value))
     */
    public function fetch($template = null, $cache_id = null, $compile_id = null, $parent = null)
    {
        // If no template specified, use parent behavior (fetches current template)
        if ($template === null) {
            return parent::fetch($template, $cache_id, $compile_id, $parent);
        }

        // Convert Smarty path to Laravel view name
        $viewName = \APP\template\TemplateManager::getManager()->smartyPathToViewName($template);

        // Fire View::resolveName hook for plugin overrides
        $aliased = null;
        Hook::call('View::resolveName', [&$aliased, $viewName]);
        if ($aliased !== null) {
            $viewName = $aliased;
        }

        // Resolve file path through Laravel
        $filePath = app('view.finder')->find($viewName);

        // Render based on resolved file type
        if (str_ends_with($filePath, '.blade')) {
            // Blade template - render with current template variables
            return view($viewName, $this->getTemplateVars())->render();
        } else {
            // Smarty template - create child template with current variables as parent
            // This preserves variable scope from $smarty->assign() calls
            return parent::fetch($filePath, $cache_id, $compile_id, $parent);
        }
    }

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
        // string: and eval: resources bypass Laravel (dynamic content)
        if (preg_match('/^(string|eval):/', $template)) {
            parent::_subTemplateRender(
                $template, $cache_id, $compile_id, $caching,
                $cache_lifetime, $data, $scope, $forceTplCache, $uid, $content_func
            );
            return;
        }

        // Strip file: prefix if present (Smarty adds this during compilation)
        if (str_starts_with($template, 'file:')) {
            $template = substr($template, 5);
        }

        // Convert Smarty path to Laravel view name
        $viewName = \APP\template\TemplateManager::getManager()->smartyPathToViewName($template);

        // Fire View::resolveName hook for plugin overrides
        $aliased = null;
        Hook::call('View::resolveName', [&$aliased, $viewName]);
        if ($aliased !== null) {
            $viewName = $aliased;
        }

        // Resolve file path through Laravel
        $filePath = app('view.finder')->find($viewName);

        // Render based on resolved file type
        if (str_ends_with($filePath, '.blade')) {
            // Blade template - render via Laravel
            $templateVars = array_merge($this->getTemplateVars(), $data ?? []);
            echo view($viewName, $templateVars)->render();
        } else {
            // Smarty template - use file: prefix with resolved path
            parent::_subTemplateRender(
                'file:' . $filePath, $cache_id, $compile_id, $caching,
                $cache_lifetime, $data, $scope, $forceTplCache, $uid, $content_func
            );
        }
    }
}
