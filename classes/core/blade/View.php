<?php

/**
 * @file classes/core/blade/View.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class View
 *
 * @brief Custom View that tracks rendering context on the Factory.
 *
 * Pushes the resolved view name onto the Factory's rendering stack before
 * rendering and pops it after, so Factory::resolveViewName() can identify
 * the calling plugin when resolving nested unnamespaced @include / view()
 * calls. See pkp/pkp-lib#12684.
 */

namespace PKP\core\blade;

class View extends \Illuminate\View\View
{
    /**
     * @see \Illuminate\View\View::renderContents()
     */
    protected function renderContents()
    {
        $factory = $this->factory; /** @var \PKP\core\blade\Factory $factory */
        $factory->pushRenderingView($this->name());
        try {
            return parent::renderContents();
        } finally {
            $factory->popRenderingView();
        }
    }
}
