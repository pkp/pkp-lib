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
 * Intercepts nested template includes and resolves them through Laravel's
 * FileViewFinder, enabling:
 * - Plugin template overrides via View::resolveName hook (with caching)
 * - Blade templates to be included from Smarty templates
 * - Unified template resolution for both engines
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

        // Convert to view name and resolve (Factory caches, hook fires once)
        $viewName = \APP\template\TemplateManager::getManager()->smartyPathToViewName($template);
        $resolvedName = app('view')->resolveViewName($viewName);
        $filePath = app('view.finder')->find($resolvedName);

        if (str_ends_with($filePath, '.blade')) {
            return view($resolvedName, $this->getTemplateVars())->render();
        }
        return parent::fetch('file:' . $filePath, $cache_id, $compile_id, $parent);
    }

    /**
     * Override sub-template rendering to route through Laravel's view system.
     *
     * Called when Smarty encounters {include file="..."} or {extends file="..."}.
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

        // Convert to view name and resolve (Factory caches, hook fires once)
        $viewName = \APP\template\TemplateManager::getManager()->smartyPathToViewName($template);
        $resolvedName = app('view')->resolveViewName($viewName);
        $filePath = app('view.finder')->find($resolvedName);

        if (str_ends_with($filePath, '.blade')) {
            $templateVars = array_merge($this->getTemplateVars(), $data ?? []);
            echo view($resolvedName, $templateVars)->render();
        } else {
            parent::_subTemplateRender(
                'file:' . $filePath, $cache_id, $compile_id, $caching,
                $cache_lifetime, $data, $scope, $forceTplCache, $uid, $content_func
            );
        }
    }
}
