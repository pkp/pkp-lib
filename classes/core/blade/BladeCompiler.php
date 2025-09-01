<?php

namespace PKP\core\blade;

use PKP\core\blade\ComponentTagCompiler;
use Illuminate\View\Compilers\BladeCompiler as IlluminateBladeCompiler;

class BladeCompiler extends IlluminateBladeCompiler
{
    /**
     * Override the compileComponentTags method to use the overridden ComponentTagCompiler
     * @see \PKP\core\blade\ComponentTagCompiler
     * @see \Illuminate\View\Compilers\BladeCompiler::compileComponentTags()
     */
    protected function compileComponentTags($value)
    {
        if (! $this->compilesComponentTags) {
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
}
