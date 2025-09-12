<?php

namespace PKP\core\blade;

use APP\core\Application;
use Illuminate\View\Compilers\ComponentTagCompiler as IlluminateComponentTagCompiler;
use PKP\core\PKPContainer;

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

        $appNamespace = Application::get()->getNamespace();
        
        if (class_exists($appNamespace.'view\\components\\'.$class)) {
            return $appNamespace.'view\\components\\'.$class;
        }

        $pkpNamespace = PKPContainer::getInstance()->getNamespace();
        
        return $pkpNamespace.'view\\components\\'.$class;
    }
}
