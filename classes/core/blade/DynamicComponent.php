<?php

/**
 * @file classes/core/blade/DynamicComponent.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DynamicComponent
 *
 * @brief This overrides the default DynamicComponent to use the overridden ComponentTagCompiler
 */

namespace PKP\core\blade;

use Illuminate\Container\Container;
use PKP\core\blade\ComponentTagCompiler;
use Illuminate\View\DynamicComponent as IlluminateDynamicComponent;

class DynamicComponent extends IlluminateDynamicComponent
{
    /**
     * Get an instance of the Blade tag compiler.
     *
     * @return \Illuminate\View\Compilers\ComponentTagCompiler
     */
    protected function compiler()
    {
        if (! static::$compiler) {
            static::$compiler = new ComponentTagCompiler(
                Container::getInstance()->make('blade.compiler')->getClassComponentAliases(),
                Container::getInstance()->make('blade.compiler')->getClassComponentNamespaces(),
                Container::getInstance()->make('blade.compiler')
            );
        }

        return static::$compiler;
    }
}
