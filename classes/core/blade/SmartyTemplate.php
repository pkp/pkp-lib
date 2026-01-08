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
 * @brief Routes Smarty {include} directives through Laravel's view system.
 *
 * Intercepts nested template includes and routes them through Laravel's
 * view() function. This ensures:
 * - Single resolution point: View::resolveName hook fires once in Factory.make()
 * - Unified engine selection: Factory picks Blade or Smarty based on file extension
 * - Plugin overrides work consistently for all template types
 */

namespace PKP\core\blade;

use Smarty_Internal_Template;

class SmartyTemplate extends Smarty_Internal_Template
{
    /**
     * Override fetch to route through Laravel's view system.
     *
     * Handles cases where Smarty plugins call $smarty->fetch() directly
     * (e.g., FormBuilderVocabulary calling $smarty->fetch('form/select.tpl'))
     */
    public function fetch($template = null, $cache_id = null, $compile_id = null, $parent = null)
    {
        // Delegate to parent: null, string:, eval:, file: (already resolved)
        if ($template === null || preg_match('/^(string|eval|file):/', $template)) {
            return parent::fetch($template, $cache_id, $compile_id, $parent);
        }

        // Route through Laravel - hook fires once in Factory.make()
        $viewName = \APP\template\TemplateManager::getManager()->smartyPathToViewName($template);
        return view($viewName, $this->getTemplateVars())->render();
    }

    /**
     * Override sub-template rendering to route through Laravel's view system.
     *
     * Called when Smarty encounters {include file="..."} directives.
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
        // Delegate to parent: string:, eval:, file: (already resolved)
        if (preg_match('/^(string|eval|file):/', $template)) {
            parent::_subTemplateRender(
                $template, $cache_id, $compile_id, $caching,
                $cache_lifetime, $data, $scope, $forceTplCache, $uid, $content_func
            );
            return;
        }

        // Route through Laravel - hook fires once in Factory.make()
        $viewName = \APP\template\TemplateManager::getManager()->smartyPathToViewName($template);
        $templateVars = array_merge($this->getTemplateVars(), $data ?? []);
        echo view($viewName, $templateVars)->render();
    }
}
