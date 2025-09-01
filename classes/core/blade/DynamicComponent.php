<?php

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
