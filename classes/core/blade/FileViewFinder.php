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
 * UNIFIED HOOK ARCHITECTURE:
 * Uses a single hook (TemplateResource::getFilename) for BOTH Blade and Smarty contexts,
 * with automatic context detection based on input parameters. This unifies the previously
 * separate BladeTemplate::getFilename and TemplateResource::getFilename hooks.
 *
 * CONTEXT DETECTION:
 * The hook handler (Plugin::_overridePluginTemplates) automatically detects context:
 *
 * Blade Context:
 *   - Called from: FileViewFinder::find()
 *   - Input: "frontend.pages.article" (dot notation, no slashes, no .tpl)
 *   - $overridePath starts as: null
 *   - Expected output: Namespaced view path OR Smarty resource notation
 *
 * Smarty Context:
 *   - Called from: PKPTemplateResource::_getFilename()
 *   - Input: "templates/frontend/pages/article.tpl" (path with slashes/extension)
 *   - $overridePath starts as: Constructed path (never null)
 *   - Expected output: Modified file path OR Blade view name
 *
 * TEMPLATE OVERRIDE MECHANISM:
 * When Laravel's view system resolves a template (e.g., @include('frontend.pages.article')),
 * this finder intercepts the resolution process and fires the TemplateResource::getFilename
 * hook BEFORE falling back to Laravel's standard path search.
 *
 * Plugins can return:
 * 1. Namespaced view path (e.g., "pluginNamespace::frontend.objects.article_details")
 * 2. Smarty resource notation (e.g., "plugins-1-...:frontend/pages/article.tpl")
 *
 * Priority order: Plugin Blade > Plugin Smarty > Core Blade
 *
 * NON-BREAKING BACKWARD COMPATIBILITY:
 * This approach enables plugins to override Blade templates with Smarty templates (.tpl)
 * when no corresponding Blade override exists. When a Smarty resource path is detected,
 * the .tpl extension is dynamically registered with the SmartyTemplatingEngine, which
 * renders the template via PKPTemplateManager.
 *
 * HOOK INTEGRATION:
 * - Hook: TemplateResource::getFilename (unified for both Blade and Smarty)
 * - Signature: Hook::call('TemplateResource::getFilename', [&$overridePath, $templateName])
 * - Parameters:
 *   - $overridePath (string|null): Reference to override path. Set by hook implementation.
 *     - null = Blade context (from FileViewFinder)
 *     - string = Smarty context (from PKPTemplateResource)
 *   - $templateName (string): Template name/path
 *     - Blade: 'frontend.pages.article' (dot notation)
 *     - Smarty: 'templates/frontend/pages/article.tpl' (file path)
 *
 * KEY FEATURES:
 * 1. Works for both top-level templates and nested @include() directives
 * 2. Supports theme hierarchy (child → parent → grandparent)
 * 3. Allows mixing Blade and Smarty overrides in the same plugin
 * 4. Dynamic .tpl extension registration prevents global Laravel conflicts
 * 5. View caching for performance (same template resolved only once per request)
 * 6. Unified hook architecture reduces complexity and maintenance burden
 *
 * TECHNICAL FLOW (Blade Context):
 * 1. Laravel calls FileViewFinder::find('frontend.pages.article')
 * 2. Hook fires: TemplateResource::getFilename with $overridePath = null
 * 3. Plugin::_overridePluginTemplates() detects Blade context (null + no slashes)
 * 4. Plugin checks for override (using file_exists() to avoid recursion):
 *    a. Check plugin Blade: plugin/templates/frontend/pages/article.blade
 *    b. Check plugin Smarty: plugin/templates/frontend/pages/article.tpl
 * 5. If Blade found: Return namespaced view → Factory stores mapping → View composers match
 * 6. If Smarty found: Return resource notation → Dynamic .tpl registration → SmartyTemplatingEngine
 * 7. If no override: Fall back to parent::find() → Standard Laravel path search
 *
 * SMARTY RESOURCE NOTATION:
 * Format: "plugins-{contextId}-{pluginPath}-{category}-{plugin}:{template/path.tpl}"
 * Example: "plugins-1-plugins-generic-apiExample-generic-apiExample:frontend/pages/article.tpl"
 *
 * This format is recognized by PKPTemplateManager's Smarty resource system and allows
 * the Smarty engine to locate plugin templates correctly.
 *
 * DYNAMIC EXTENSION REGISTRATION (Critical Implementation Detail):
 * When a Smarty resource is detected (contains ':' and ends with '.tpl'), the .tpl extension
 * is registered just-in-time with Laravel's view system:
 *
 *   View::addExtension('tpl', 'smarty');
 *
 * This happens AFTER path resolution is complete, preventing Laravel from treating Smarty
 * resource notation as a file path during resolution (which would cause path mangling).
 *
 * WHY DYNAMIC REGISTRATION IS ESSENTIAL:
 * - Global registration breaks: Laravel tries to normalize Smarty resource paths as file paths
 *   Example: "plugins-...:article.tpl" becomes "defaultthemeplugin::.Users.abir..." (mangled)
 * - JIT registration works: Path is already resolved and cached before Laravel knows .tpl exists
 * - Result: Opaque resource notation during resolution, proper engine selection during rendering
 *
 * NULL CHECK PATTERN:
 * The merged hook implementation uses null checking to distinguish contexts:
 *
 * ```php
 * public function _overridePluginTemplates($hookName, $args) {
 *     $overridePath = &$args[0];
 *     $templatePath = $args[1];
 *
 *     // Detect Blade context
 *     $isBladeContext = !str_contains($templatePath, '/') && !str_ends_with($templatePath, '.tpl');
 *
 *     if ($isBladeContext) {
 *         // Handle Blade → Blade or Blade → Smarty override
 *     } else {
 *         // Smarty context guard: FileViewFinder passes null, PKPTemplateResource never does
 *         if (is_null($overridePath)) {
 *             return Hook::CONTINUE;  // Not Smarty context, skip
 *         }
 *         // Handle Smarty → Smarty or Smarty → Blade override
 *     }
 * }
 * ```
 *
 * USAGE EXAMPLE (Plugin Implementation):
 *
 * ```php
 * // In plugin's register() method:
 * Hook::add('TemplateResource::getFilename', $this->_overridePluginTemplates(...));
 *
 * // Single hook registration handles BOTH Blade and Smarty contexts automatically!
 * ```
 *
 * SECURITY CONSIDERATIONS:
 * - Only .blade extension is supported for security (not .blade.php)
 * - file_exists() is used instead of view()->exists() to prevent recursion
 * - Plugin template paths are validated before returning
 * - Smarty resource notation prevents path traversal attacks
 *
 * PERFORMANCE OPTIMIZATIONS:
 * - View resolution results are cached in $this->views[] array
 * - Same template name is resolved only once per request
 * - Hook fires only for uncached views
 * - Dynamic extension registration happens once per request (persists after first use)
 *
 * DEPRECATION PATH:
 * When Smarty support is eventually removed:
 * 1. Remove Smarty resource path handling (lines with $isSmartyResource)
 * 2. Remove dynamic .tpl extension registration
 * 3. Remove SmartyTemplatingEngine class
 * 4. Keep unified hook system for Blade-to-Blade overrides
 * 5. Simplify Plugin::_overridePluginTemplates to only handle Blade
 *
 * @see PKP\core\blade\SmartyTemplatingEngine
 * @see PKP\plugins\Plugin::_overridePluginTemplates()
 * @see PKP\template\PKPTemplateResource
 * @see PKP\core\PKPBladeViewServiceProvider::registerEngineResolver()
 */

namespace PKP\core\blade;

use PKP\plugins\Hook;
use Illuminate\Support\Facades\View;

class FileViewFinder extends \Illuminate\View\FileViewFinder
{
    /**
     * Track view overrides for the Factory to use
     */
    protected array $viewOverrides = [];

    /**
     * Get the specific override for a view if it exists
     */
    public function getViewOverride(string $view): ?string
    {
        return $this->viewOverrides[$view] ?? null;
    }

    /**
     * Get all view overrides mapping (original => namespaced)
     *
     * Used by Factory::callComposer() for reverse lookup to fire
     * composers registered on original view names.
     */
    public function getViewOverrides(): array
    {
        return $this->viewOverrides;
    }

    /**
     * Manually add a view override mapping
     *
     * Used by PKPTemplateResource when Smarty context overrides to Blade,
     * since the normal mapping flow doesn't occur (view name is already namespaced
     * when passed to Factory::make()).
     *
     * This enables Factory::callComposer() to fire composers registered on
     * the original (non-namespaced) view name for Smarty → Blade overrides.
     *
     * @param string $original Original view name in dot notation (e.g., 'frontend.pages.article')
     * @param string $namespaced Namespaced view path (e.g., 'pluginNamespace::frontend.pages.article')
     */
    public function addViewOverride(string $original, string $namespaced): void
    {
        $this->viewOverrides[$original] = $namespaced;
    }

    /**
     * Find the given view in the list of paths.
     *
     * Fires a unified hook to allow plugins to override the view path
     * before falling back to standard Laravel resolution.
     *
     * This method is called by Laravel's view system when resolving template names.
     * It intercepts the resolution process to enable plugin-based template overrides
     * for both Blade and Smarty templates.
     *
     * @hook TemplateResource::getFilename [[&$overridePath, $name]]
     *
     * @param string $name View name in dot notation (e.g., 'frontend.pages.article')
     * @return string Resolved file path or Smarty resource notation
     *
     * @throws \InvalidArgumentException When view cannot be found
     */
    public function find($name)
    {
        // Check cache first to avoid firing hook multiple times
        if (isset($this->views[$name])) {
            return $this->views[$name];
        }

        // Initialize override path
        $overridePath = null;

        // Fire hook: allow plugins to provide override
        Hook::call('TemplateResource::getFilename', [&$overridePath, $name]);

        // If plugin provided an override, handle it
        if ($overridePath !== null) {
            // Check if this is a Smarty resource notation (e.g., "plugins-...:template.tpl")
            // Smarty resources don't exist as files, so skip file existence check
            $isSmartyResource = str_contains($overridePath, ':') && str_ends_with($overridePath, '.tpl');

            if ($isSmartyResource) {
                // NON-BREAKING BACKWARD COMPATIBILITY
                // Register .tpl extension to use Smarty engine for rendering
                // This allows plugins to override Blade templates with Smarty templates
                // Priority order: Plugin Blade > Plugin Smarty > Core Blade
                View::addExtension('tpl', 'smarty');

                // Return Smarty resource directly - it will be handled by SmartyTemplatingEngine
                $this->views[$name] = $overridePath;
                return $overridePath;
            }

            // Check if this is a namespaced view override
            if (strpos($overridePath, '::') !== false) {
                // Store the override mapping for Factory to use
                $this->viewOverrides[$name] = $overridePath;

                // Resolve the namespaced view to get the actual path
                // Note: parent::find() also caches $this->views[$overridePath] internally,
                // so we only need to cache the original name here
                $resolved = parent::find($overridePath);

                $this->views[$name] = $resolved;

                return $resolved;
            }

            // For regular file paths (Blade), verify the file exists
            if ($this->files->exists($overridePath)) {
                $this->views[$name] = $overridePath;
                return $overridePath;
            }
        }

        // Fall back to standard Laravel resolution
        // This checks registered paths in order: theme → app → pkp
        return parent::find($name);
    }
}
