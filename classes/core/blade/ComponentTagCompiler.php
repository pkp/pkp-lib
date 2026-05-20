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
 * @brief   Override Laravel's mechanism for guessing the class of an
 *          un-prefixed Blade component tag (e.g. <x-foo />) so that themes
 *          can shadow it via the Component::resolveClass hook — the
 *          class-resolution analog of View::resolveName.
 */

namespace PKP\core\blade;

use APP\core\Application;
use Illuminate\View\Compilers\ComponentTagCompiler as IlluminateComponentTagCompiler;
use PKP\core\PKPBladeViewServiceProvider;
use PKP\plugins\Hook;

class ComponentTagCompiler extends IlluminateComponentTagCompiler
{
    /**
     * Resolve the class for an un-prefixed component tag.
     *
     * Resolution order mirrors the View::resolveName policy:
     *   1. Component::resolveClass hook — themes can shadow with their own class.
     *   2. view.components.namespace config — app then pkp
     *      (registered in PKPContainer.php:583-585).
     *   3. Fallback to the application namespace.
     *
     * Prefixed tags (e.g. <x-funding::list-funders />) never reach this method;
     * they are handled by Laravel's findClassByComponent (vendor line 400)
     * via the namespaces registered through _registerViewComponentNamespace().
     *
     * @see Illuminate\View\Compilers\ComponentTagCompiler::guessClassName()
     */
    public function guessClassName(string $component): string
    {
        $class = $this->formatClassName($component);

        // 1. Theme override via hook. Symmetric with View::resolveName.
        $overrideClass = null;
        Hook::call('Component::resolveClass', [$component, &$overrideClass]);
        if ($overrideClass !== null && class_exists($overrideClass)) {
            return $overrideClass;
        }

        // 2. App / PKP namespaces from config, in priority order.
        $componentNamespaces = config('view.components.namespace'); /** @var array $componentNamespaces */
        foreach ($componentNamespaces as $viewNamespace) {
            $candidate = rtrim($viewNamespace, '\\') . '\\' . $class;
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        // 3. Default fallback. Returns a well-formed FQCN even when no class
        //    exists, so Laravel's caller can detect "no class" and fall
        //    through to anonymous-component resolution.
        return Application::get()->getNamespace() . PKPBladeViewServiceProvider::VIEW_NAMESPACE_PATH . $class;
    }
}
