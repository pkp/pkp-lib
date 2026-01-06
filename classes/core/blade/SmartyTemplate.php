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
 * @brief Custom Smarty template class that routes ALL template includes through Laravel's view system
 *
 * This class extends Smarty_Internal_Template to intercept nested template includes
 * ({include file="..."}) and route them through Laravel's view system. This enables:
 *
 * 1. UNIFIED RESOLUTION: All templates resolve through Laravel's FileViewFinder,
 *    with view name aliasing via the View::alias hook. This ensures plugins can
 *    override any template, regardless of nesting depth or resource prefix.
 *
 * 2. SINGLE CODE PATH: By routing app:, core:, and plain templates all through
 *    FileViewFinder, we reduce code paths and ensure consistent behavior.
 *
 * 3. CROSS-ENGINE SUPPORT: When a Smarty template includes a template that has been
 *    overridden with a Blade file, this class handles the transition by rendering
 *    the Blade template via Laravel's view() and injecting the output.
 *
 * 4. VIEW COMPOSERS: Laravel view composers can be triggered for Smarty templates,
 *    allowing data to be attached to views declaratively.
 *
 * RESOURCE PREFIX HANDLING:
 *
 * Only true Smarty built-in resources bypass Laravel resolution:
 * - file: Direct file path (Smarty internal)
 * - string: Inline template string
 * - eval: Evaluate string as template
 *
 * PKP resource prefixes route through Laravel:
 * - app: → app:: namespace (templates/)
 * - core: → pkp:: namespace (lib/pkp/templates/)
 * - (plain) → default resolution
 *
 * HOW IT WORKS:
 *
 * When Smarty encounters {include file="core:frontend/pages/submissions.tpl"}:
 * 1. _subTemplateRender() is called
 * 2. isSmartyBuiltinResource() returns false (core: is not file:/string:/eval:)
 * 3. smartyPathToViewName() converts to "pkp::frontend.pages.submissions"
 * 4. View::alias hook fires, allowing plugins to alias to namespaced view
 * 5. FileViewFinder resolves the (possibly aliased) view name
 * 6. Based on resolved path: Blade renders directly, Smarty gets file: prefix
 *
 * @see PKP\core\blade\SmartyTemplatingEngine
 * @see PKP\core\blade\FileViewFinder
 */

namespace PKP\core\blade;

use PKP\plugins\Hook;
use Smarty_Internal_Template;

class SmartyTemplate extends Smarty_Internal_Template
{
    /**
     * Override sub-template rendering to route through Laravel's view finder
     *
     * This intercepts Smarty's {include} directive and resolves the template
     * path through Laravel's FileViewFinder, enabling unified template resolution
     * and plugin overrides for nested templates.
     *
     * @param string $template Template name (e.g., "frontend/pages/article.tpl")
     * @param mixed $cache_id Cache ID
     * @param mixed $compile_id Compile ID
     * @param int $caching Caching mode
     * @param int $cache_lifetime Cache lifetime
     * @param array $data Template variables
     * @param int $scope Variable scope
     * @param bool $forceTplCache Force template cache
     * @param string|null $uid Unique identifier
     * @param string|null $content_func Content function
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
        // Check if template has a Smarty built-in resource prefix (file:, string:, eval:)
        // These are special Smarty resources that should be handled directly by Smarty
        if ($this->isSmartyBuiltinResource($template)) {
            parent::_subTemplateRender(
                $template,
                $cache_id,
                $compile_id,
                $caching,
                $cache_lifetime,
                $data,
                $scope,
                $forceTplCache,
                $uid,
                $content_func
            );
            return;
        }

        // Convert Smarty template name to Laravel view name and resolve through FileViewFinder
        // This handles: plain paths, core:, app:, and plugin resource notations
        $resolved = $this->resolveTemplateThroughLaravel($template);

        if ($resolved === null) {
            // Not found in Laravel's paths, fall back to Smarty's default resolution
            parent::_subTemplateRender(
                $template,
                $cache_id,
                $compile_id,
                $caching,
                $cache_lifetime,
                $data,
                $scope,
                $forceTplCache,
                $uid,
                $content_func
            );
            return;
        }

        // Handle based on resolved path type
        if ($this->isBladeFile($resolved)) {
            // Blade file: Render via Laravel and output the result
            // Pass the view name so View::composer() patterns match correctly
            $viewName = $this->templateToViewName($template);
            $this->renderBladeAndOutput($viewName, $data);
            return;
        }

        // Smarty .tpl file: Use file: prefix to bypass Smarty's resource resolution
        // since we already resolved the absolute path through Laravel
        parent::_subTemplateRender(
            'file:' . $resolved,
            $cache_id,
            $compile_id,
            $caching,
            $cache_lifetime,
            $data,
            $scope,
            $forceTplCache,
            $uid,
            $content_func
        );
    }

    /**
     * Check if template uses a Smarty built-in resource type
     *
     * These are special Smarty resources that should bypass Laravel resolution:
     * - file: Direct file path (Smarty internal)
     * - string: Inline template string
     * - eval: Evaluate string as template
     *
     * Note: app: and core: are NOT built-in - they are PKP custom resources
     * that we now route through Laravel's FileViewFinder for unified resolution.
     *
     * @param string $template Template name
     * @return bool True if Smarty built-in resource
     */
    protected function isSmartyBuiltinResource(string $template): bool
    {
        // Only bypass for true Smarty built-in resources
        // These are processed directly by Smarty's internal handlers
        return preg_match('/^(file|string|eval):/', $template) === 1;
    }

    /**
     * Check if the resolved path is a Blade file
     *
     * @param string $path Resolved path
     * @return bool True if Blade file
     */
    protected function isBladeFile(string $path): bool
    {
        return str_ends_with($path, '.blade') || str_ends_with($path, '.blade.php');
    }

    /**
     * Render a Blade template and output the result into Smarty's output
     *
     * Uses view() with the view name (not View::file()) to ensure:
     * - View::composer() patterns match correctly
     * - Laravel's view event system works properly
     * - FileViewFinder's cached resolution is used (no double lookup)
     *
     * @param string $viewName Laravel view name in dot notation (e.g., "frontend.components.sidebar")
     * @param array $data Template variables to pass
     */
    protected function renderBladeAndOutput(string $viewName, array $data): void
    {
        // Merge Smarty's current template variables with passed data
        $templateVars = array_merge($this->getTemplateVars(), $data ?? []);

        // Render using view() with the view name
        // This triggers View::composer() patterns and uses cached resolution
        $output = view($viewName, $templateVars)->render();

        // Output directly - Smarty will capture this in the parent template
        echo $output;
    }

    /**
     * Convert Smarty template name to Laravel view name and resolve through FileViewFinder
     *
     * Fires the View::alias hook to allow plugins to override the view name,
     * mirroring the behavior in Factory::make().
     *
     * @param string $template Smarty template name (e.g., "frontend/pages/article.tpl")
     * @return string|null Resolved absolute file path, or null if not found
     */
    protected function resolveTemplateThroughLaravel(string $template): ?string
    {
        try {
            // Convert Smarty path to Laravel view name
            // "frontend/pages/article.tpl" → "frontend.pages.article"
            $viewName = $this->templateToViewName($template);

            // Fire alias hook (same as Factory::make does) to allow plugins to override
            $aliased = null;
            Hook::call('View::alias', [&$aliased, $viewName]);

            if ($aliased !== null && $aliased !== $viewName) {
                $viewName = $aliased;
            }

            // Get Laravel's view finder
            $finder = app('view.finder');

            // Resolve through FileViewFinder using standard Laravel resolution
            return $finder->find($viewName);
        } catch (\InvalidArgumentException $e) {
            // View not found in Laravel's paths, fall back to Smarty's resolution
            return null;
        }
    }

    /**
     * Convert Smarty template path to Laravel view name
     *
     * Delegates to PKPTemplateManager::smartyPathToViewName() for comprehensive
     * handling of all input formats (namespaces, resource prefixes, etc.)
     *
     * @param string $template Smarty template path
     * @return string Laravel view name in dot notation
     */
    protected function templateToViewName(string $template): string
    {
        return \APP\template\TemplateManager::getManager()->smartyPathToViewName($template);
    }
}
