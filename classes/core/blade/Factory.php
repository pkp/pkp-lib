<?php

/**
 * @file classes/core/blade/Factory.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Factory
 *
 * @brief Custom Laravel View Factory with view name aliasing support
 *
 * Extends Laravel's View Factory to enable plugin template overrides via view
 * name aliasing. When a plugin overrides a core template, this factory:
 *
 * 1. Transforms the view name to a namespaced version (e.g., 'frontend.pages.article'
 *    becomes 'pluginName::frontend.pages.article')
 * 2. Lets Laravel's standard namespace resolution find the file
 * 3. Fires view composers for BOTH the original and aliased names
 *
 * This enables:
 * - Core code to register View::composer('frontend.pages.article', ...) callbacks
 *   that fire even when plugins override those templates
 * - Plugin code to register View::composer('pluginName::*', ...) callbacks
 *   that fire for their namespaced templates
 * - Automatic $viewNamespace variable via existing plugin composer registration
 *
 * @see PKP\plugins\Plugin::_overridePluginTemplates()
 */

namespace PKP\core\blade;

use Illuminate\Contracts\View\View as ViewContract;
use PKP\plugins\Hook;

class Factory extends \Illuminate\View\Factory
{
    /**
     * Mapping of original view names to their aliased (namespaced) versions
     * e.g., ['frontend.pages.article' => 'defaultmanuscripttheme::frontend.pages.article']
     */
    protected array $aliases = [];

    /**
     * Create a new view instance.
     *
     * Fires a hook to allow plugins to alias the view name before resolution.
     * If aliased, the view is created with the namespaced name, enabling
     * proper composer pattern matching.
     *
     * @param string $view View name
     * @param array $data Data to pass to view
     * @param array $mergeData Additional data to merge
     * @return \Illuminate\Contracts\View\View
     */
    public function make($view, $data = [], $mergeData = [])
    {
        $original = $this->normalizeName($view);

        // Hook: allow plugins to alias this view name
        $aliased = null;
        Hook::call('View::alias', [&$aliased, $original]);

        if ($aliased !== null && $aliased !== $original) {
            $this->aliases[$original] = $aliased;
            $view = $aliased;
        }

        return parent::make($view, $data, $mergeData);
    }

    /**
     * Call the composer for a given view.
     *
     * Fires composers for both the actual view name AND the original name
     * if this view was aliased. This enables core code to register composers
     * on original view names that work even when plugins override templates.
     *
     * @param \Illuminate\Contracts\View\View $view
     * @return void
     */
    public function callComposer(ViewContract $view)
    {
        // Fire composers for the actual view name (namespaced if aliased)
        parent::callComposer($view);

        // Also fire for original name via reverse lookup
        $original = array_search($view->name(), $this->aliases, true);
        if ($original !== false) {
            $event = 'composing: ' . $original;
            if ($this->events->hasListeners($event)) {
                $this->events->dispatch($event, [$view]);
            }
        }
    }

    /**
     * Call the creator for a given view.
     *
     * Same as callComposer - fires for both aliased and original names.
     * Creators fire at instantiation time (before composers).
     *
     * @param \Illuminate\Contracts\View\View $view
     * @return void
     */
    public function callCreator(ViewContract $view)
    {
        // Fire creators for the actual view name
        parent::callCreator($view);

        // Also fire for original name via reverse lookup
        $original = array_search($view->name(), $this->aliases, true);
        if ($original !== false) {
            $event = 'creating: ' . $original;
            if ($this->events->hasListeners($event)) {
                $this->events->dispatch($event, [$view]);
            }
        }
    }

    /**
     * Get all aliases (for debugging/testing)
     *
     * @return array Map of original => aliased view names
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }
}
