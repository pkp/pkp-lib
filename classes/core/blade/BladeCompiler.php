<?php

/**
 * @file classes/core/blade/BladeCompiler.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BladeCompiler
 *
 * @brief Extended BladeCompiler that supports Smarty template includes.
 */

namespace PKP\core\blade;

use Illuminate\View\Compilers\BladeCompiler as IlluminateBladeCompiler;
use PKP\template\PKPTemplateResource;

class BladeCompiler extends IlluminateBladeCompiler
{
    /**
     * Override the compileComponentTags method to use the overridden ComponentTagCompiler
     * @see \PKP\core\blade\ComponentTagCompiler
     * @see \Illuminate\View\Compilers\BladeCompiler::compileComponentTags()
     */
    protected function compileComponentTags($value)
    {
        if (!$this->compilesComponentTags) {
            return $value;
        }

        return (
            new ComponentTagCompiler(
                $this->classComponentAliases,
                $this->classComponentNamespaces,
                $this
            )
        )->compile($value);
    }

    /**
     * Override compileInclude to support Smarty template includes from Blade.
     *
     * Uses PKPTemplateResource::getFilePath() to resolve the template.
     * If it resolves to a .tpl file, generates code to render via Smarty.
     * If it resolves to a .blade file, uses View::file() for direct rendering.
     *
     * @see \Illuminate\View\Compilers\Concerns\CompilesIncludes::compileInclude()
     *
     * @param string $expression The @include expression
     *
     * @return string Compiled PHP code
     */
    protected function compileInclude($expression)
    {
        $expression = $this->stripParentheses($expression);

        // Extract the view name from the expression
        $viewName = $this->extractViewName($expression);

        if ($viewName) {
            $filePath = PKPTemplateResource::getFilePath($viewName);

            if ($filePath) {
                // If it's a Smarty template, render via TemplateManager
                if (!PKPTemplateResource::isBladeTemplate($filePath)) {
                    $normalizedName = PKPTemplateResource::normalizeTemplateName($viewName);
                    return "<?php echo \\APP\\template\\TemplateManager::getManager(\\APP\\core\\Application::get()->getRequest())->fetch('{$normalizedName}.tpl'); ?>";
                }

                // For Blade templates, use View::file() with the resolved path
                $escapedPath = addslashes($filePath);
                return "<?php echo \\Illuminate\\Support\\Facades\\View::file('{$escapedPath}', \\Illuminate\\Support\\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>";
            }
        }

        // Default behavior: use Laravel's view system
        return parent::compileInclude($expression);
    }

    /**
     * Extract the view name from the @include expression.
     *
     * @param string $expression The expression (e.g., "'frontend.objects.article_details'" or "'view', ['data']")
     *
     * @return string|null The view name without quotes, or null if cannot extract
     */
    protected function extractViewName(string $expression): ?string
    {
        // Match single or double quoted string at the start
        if (preg_match('/^["\']([^"\']+)["\']/', $expression, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
