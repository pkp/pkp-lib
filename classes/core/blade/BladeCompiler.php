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
 * @brief This overrides the default BladeCompiler to use the overridden ComponentTagCompiler
 */

namespace PKP\core\blade;

use PKP\core\blade\ComponentTagCompiler;
use Illuminate\View\Compilers\BladeCompiler as IlluminateBladeCompiler;
use PKP\plugins\ThemePlugin;
use APP\core\Application;
use APP\template\TemplateManager;

class BladeCompiler extends IlluminateBladeCompiler
{
    /**
     * Cache for active theme plugin to avoid repeated lookups
     * @var ThemePlugin|null|false Null = not checked yet, false = no theme, ThemePlugin = cached instance
     */
    protected static $cachedThemePlugin = null;

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
     * FIXME : Perhaps we can remove it as `BladeTemplate::getFilename` works ??
     * Override compileInclude to check for plugin Smarty template overrides
     * @see \Illuminate\View\Compilers\Concerns\CompilesIncludes::compileInclude()
     * 
     * Priority:
     * 1. Plugin Blade template (handled by view finder via prependLocation)
     * 2. Plugin Smarty template (handled here)
     * 3. Core Blade template (default Laravel behavior)
     *
     * @param string $expression The @include expression
     * @return string Compiled PHP code
     */
    /*
    protected function compileInclude($expression)
    {
        $expression = $this->stripParentheses($expression);

        // Extract the view name from the expression
        // where the expression can be like 'view.name' or 'view.name', ['data' => 'value']
        $viewName = $this->extractViewName($expression);
        
        if ($viewName) {
            $smartyOverride = $this->checkPluginSmartyOverride($viewName);
            
            if ($smartyOverride) {
                // Generate code to render via Smarty instead of Blade
                return "<?php echo \\APP\\template\\TemplateManager::getManager(\\APP\\core\\Application::get()->getRequest())->fetch('{$smartyOverride}'); ?>";
            }
        }

        // Default behavior: fallbak to Laravel's view system
        return parent::compileInclude($expression);
    }
    */

    /**
     * Extract the view name from the @include expression
     *
     * @param string $expression The expression (e.g., "'frontend.objects.article_details'" or "'view', ['data']")
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

    /**
     * Check if active theme plugin has a Smarty template override for the given view
     *
     * @param string $viewName View name in dot notation (e.g., 'frontend.objects.article_details')
     * @return string|null Smarty template path if override exists, null otherwise
     */
    protected function checkPluginSmartyOverride(string $viewName): ?string
    {
        $themePlugin = $this->getActiveThemePlugin();
        if (!$themePlugin) {
            return null;
        }

        // First check if plugin has a Blade override
        // If it does, we should NOT use the Smarty override
        $bladeViewPath = $themePlugin->resolveBladeViewPath($viewName);
        if (view()->exists($bladeViewPath)) {
            // Plugin has Blade version, let Laravel handle it
            return null;
        }

        // Check if plugin has Smarty override
        $smartyTemplatePath = $themePlugin->convertToSmartyTemplatePath($viewName);
        $overridePath = $themePlugin->_findOverriddenTemplate($smartyTemplatePath);

        if ($overridePath && file_exists($overridePath)) {
            // Return relative Smarty template path (not absolute) for TemplateManager::fetch()
            return $smartyTemplatePath;
        }

        return null;
    }

    /**
     * Get the active theme plugin with caching
     *
     * @return ThemePlugin|null
     */
    protected function getActiveThemePlugin(): ?ThemePlugin
    {
        // Check static cache first
        if (self::$cachedThemePlugin !== null) {
            return self::$cachedThemePlugin === false ? null : self::$cachedThemePlugin;
        }

        try {
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            
            if (!$context) {
                self::$cachedThemePlugin = false;
                return null;
            }

            $activeThemePath = $context->getData('themePluginPath');
            
            if (!$activeThemePath) {
                self::$cachedThemePlugin = false;
                return null;
            }
            
            $themePlugin = TemplateManager::getManager()->getTemplateVars('activeTheme')
                ?? \PKP\plugins\PluginRegistry::getPlugin('themes', $activeThemePath);
            
            if ($themePlugin && $themePlugin instanceof ThemePlugin) {
                self::$cachedThemePlugin = $themePlugin;
                return $themePlugin;
            }
        } catch (\Exception $e) {
            error_log("error resolving child smarty template override for blade @include via plugin : {$e->getMessage()}");
            throw $e; // FIXME: Should just return null so that it does not break ?
        }

        self::$cachedThemePlugin = false;
        return null;
    }
}
