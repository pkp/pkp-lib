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
 * @brief   Override the Laravel's internal mechanism to guess the name of the given component based on
 *          the priority order coming from view config
 */

namespace PKP\core\blade;

use APP\core\Application;
use PKP\core\PKPBladeViewServiceProvider;
use Illuminate\View\Compilers\ComponentTagCompiler as IlluminateComponentTagCompiler;

class ComponentTagCompiler extends IlluminateComponentTagCompiler
{
    /**
     * Overridden class to guess the name of the given component
     * 
     * @see Illuminate\View\Compilers\ComponentTagCompiler::guessClassName()
     */
    public function guessClassName(string $component): string
    {
        $class = $this->formatClassName($component);

        $componentNamespaces = config('view.components.namespace'); /** @var array $componentNamespaces */
        
        foreach ($componentNamespaces as $namespace => $viewNamespace) {
            if (class_exists($viewNamespace . $class)) {
                return $namespace . $class;
            }
        }

        // fallback to default app namespace
        return Application::get()->getNamespace() . PKPBladeViewServiceProvider::VIEW_NAMESPACE_PATH . $class;
    }
}
