<?php

/**
 * @file classes/core/blade/ComponentTagCompiler.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ComponentTagCompiler
 *
 * @brief Overrides Laravel's component compiler so that:
 *  - guessClassName() walks the OJS class-namespace map (config('view.components.namespace'))
 *  - unnamespaced anonymous components inside a plugin template are looked up
 *    under the plugin's view namespace first (pkp/pkp-lib#12684)
 */

namespace PKP\core\blade;

use APP\core\Application;
use Illuminate\Contracts\View\Factory;
use PKP\core\PKPBladeViewServiceProvider;
use Illuminate\View\Compilers\ComponentTagCompiler as IlluminateComponentTagCompiler;

class ComponentTagCompiler extends IlluminateComponentTagCompiler
{
    /**
     * Reverse map: absolute hint path -> view namespace. Built lazily per
     * compiler instance (one instance per template compile).
     */
    protected ?array $pathToNamespace = null;

    /**
     * Overridden class to guess the name of the given component.
     *
     * Lookup order:
     *   1. Calling plugin's class namespace (when compiling a plugin template).
     *      Lets a plugin template use <x-citation-style /> for its own class component
     *      without the explicit `<x-myplugin::...>` prefix. Symmetric with the
     *      anonymous-component scoping at guessAnonymousComponentUsingNamespaces().
     *      See pkp/pkp-lib#12684.
     *   2. App / pkp class component namespaces from config('view.components.namespace').
     *   3. Fallback (vendor will class_exists() this then drop through to anonymous lookup).
     *
     * @see Illuminate\View\Compilers\ComponentTagCompiler::guessClassName()
     */
    public function guessClassName(string $component): string
    {
        $class = $this->formatClassName($component);

        // 1. Calling plugin's class namespace (only when compiling a plugin template).
        $pluginNs = $this->pluginNamespaceForCurrentCompile();
        if ($pluginNs !== null) {
            $classNamespaces = $this->blade->getClassComponentNamespaces();
            if (isset($classNamespaces[$pluginNs])) {
                $candidate = $classNamespaces[$pluginNs] . '\\' . $class;
                if (class_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        // 2. App / pkp class component namespaces.
        $componentNamespaces = config('view.components.namespace'); /** @var array $componentNamespaces */
        foreach ($componentNamespaces as $namespace => $viewNamespace) {
            if (class_exists($viewNamespace . $class)) {
                return $namespace . $class;
            }
        }

        // 3. Fallback to default app namespace.
        return Application::get()->getNamespace() . PKPBladeViewServiceProvider::VIEW_NAMESPACE_PATH . $class;
    }

    /**
     * Scoped anonymous component lookup.
     *
     * If $component is unnamespaced AND we're compiling a .blade file that
     * lives inside a plugin's templates/ directory, try
     * {pluginNs}::components.{component} first. If that view exists, return
     * it so the vendor compiler renders the plugin's anonymous component.
     * Otherwise fall through to vendor lookup (which after the #12684 fix
     * only sees app/pkp anonymous component namespaces).
     *
     * @see Illuminate\View\Compilers\ComponentTagCompiler::guessAnonymousComponentUsingNamespaces()
     */
    protected function guessAnonymousComponentUsingNamespaces(Factory $viewFactory, string $component)
    {
        if (!str_contains($component, '::')) {
            $pluginNs = $this->pluginNamespaceForCurrentCompile();
            if ($pluginNs !== null) {
                // guessViewName('widget') -> 'components.widget'
                // -> $viewName = 'pluginNs::components.widget'
                $viewName = $pluginNs . '::' . $this->guessViewName($component);
                if ($viewFactory->exists($viewName)) {
                    return $viewName;
                }
                // Index-form parity with vendor lookup
                if ($viewFactory->exists($viewName . '.index')) {
                    return $viewName . '.index';
                }
            }
        }

        return parent::guessAnonymousComponentUsingNamespaces($viewFactory, $component);
    }

    /**
     * Identify the plugin view namespace owning the .blade file currently
     * being compiled, by reverse-looking-up FileViewFinder::getHints()
     * against BladeCompiler::getPath(). Returns null for non-plugin paths
     * or when no path is set.
     */
    protected function pluginNamespaceForCurrentCompile(): ?string
    {
        $path = $this->blade->getPath();
        if (!is_string($path) || $path === '') {
            return null;
        }

        if ($this->pathToNamespace === null) {
            $this->pathToNamespace = [];
            $hints = app('view.finder')->getHints();
            foreach ($hints as $namespace => $paths) {
                if ($namespace === 'app' || $namespace === 'pkp') {
                    continue;
                }
                foreach ((array) $paths as $hintPath) {
                    $this->pathToNamespace[rtrim($hintPath, '/')] = $namespace;
                }
            }
            // Longest-prefix match: sort by descending path length so that
            // the most specific (deepest) namespace wins when one plugin's
            // templates directory is nested inside another (e.g. a test
            // scaffold) or when multiple hints share a common prefix.
            uksort($this->pathToNamespace, fn ($a, $b) => strlen($b) - strlen($a));
        }

        foreach ($this->pathToNamespace as $hintPath => $namespace) {
            if (str_starts_with($path, $hintPath . '/')) {
                return $namespace;
            }
        }
        return null;
    }
}
