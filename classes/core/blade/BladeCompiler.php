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
