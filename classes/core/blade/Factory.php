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
 * @brief Custom Laravel View Factory with plugin template override support
 *
 * Extends Laravel's View Factory to ensure view composers and creators fire correctly
 * when plugins override core templates. Key responsibilities:
 *
 * 1. Call find() before getOverriddenView() so viewOverrides mapping is populated
 * 2. Create view instances with namespaced names for composer/creator pattern matching
 * 3. Fire composers/creators for BOTH namespaced AND original view names via reverse lookup
 *
 * This enables core code to register View::composer() or View::creator() callbacks on
 * original view names (e.g., 'frontend.pages.article') that will fire even when plugins
 * override those templates to namespaced versions (e.g., 'pluginNamespace::frontend.pages.article').
 *
 * @see PKP\core\blade\FileViewFinder
 * @see PKP\template\PKPTemplateResource::registerViewOverrideMapping()
 */

namespace PKP\core\blade;

use Illuminate\Contracts\View\View as ViewContract;
use PKP\core\blade\FileViewFinder;

class Factory extends \Illuminate\View\Factory
{
    /**
     * Mapping of original view names to their overridden namespaced versions
     */
    protected array $viewOverrides = [];

    /**
     * Create a new view instance.
     *
     * This method is overridden to support plugin template overrides with proper
     * view composer matching. The key insight is that we must call find() BEFORE
     * getOverriddenView() so that the FileViewFinder has a chance to populate
     * the viewOverrides mapping.
     *
     * FLOW:
     * 1. find() fires the TemplateResource::getFilename hook
     * 2. If plugin returns override, FileViewFinder stores mapping in viewOverrides
     * 3. getOverriddenView() retrieves the namespaced view name from mapping
     * 4. viewInstance() creates View with namespaced name, enabling composer matching
     *
     * @param string $view
     * @param array $data
     * @param array $mergeData
     * @return \Illuminate\View\View
     */
    public function make($view, $data = [], $mergeData = [])
    {
        $view = $this->normalizeName($view);

        // IMPORTANT: Call find() FIRST to populate viewOverrides in FileViewFinder
        // This allows getOverriddenView() to find the mapping
        $path = $this->finder->find($view);

        // Now check if this view has been overridden (mapping is now available)
        $actualView = $this->getOverriddenView($view);

        // Merge data like parent does
        $data = array_merge($mergeData, $this->parseData($data));

        // Create view instance with the TRANSFORMED name if overridden
        // This ensures view composers registered on namespace patterns will match
        // IMPORTANT: Use tap() to call callCreator() after view instance is created
        // This matches Laravel's original make() behavior
        return tap($this->viewInstance($actualView, $path, $data), function ($view) {
            $this->callCreator($view);
        });
    }

    /**
     * Call the composer for a given view.
     *
     * Overridden to also fire composers registered on the ORIGINAL view name
     * when the view has been overridden by a plugin. This enables core code
     * to register composers that work even when plugins override templates.
     *
     * Example:
     *   - Core registers: View::composer('frontend.pages.article', fn($v) => $v->with('data', ...))
     *   - Plugin overrides to: 'pluginNamespace::frontend.pages.article'
     *   - Without this override: Core composer wouldn't fire (name mismatch)
     *   - With this override: Core composer fires for plugin's template too
     *
     * @param \Illuminate\Contracts\View\View $view
     * @return void
     */
    public function callComposer(ViewContract $view)
    {
        // Call composers for the actual view name (e.g., "pluginNamespace::frontend.pages.article")
        parent::callComposer($view);

        // If this view is an override, also fire composers for the original name
        if ($this->finder instanceof FileViewFinder) {
            $viewOverrides = $this->finder->getViewOverrides();

            // Find the original name by reverse lookup
            // viewOverrides maps: original => namespaced
            // We need to find: which original maps to $view->name()
            $originalName = array_search($view->name(), $viewOverrides, true);

            if ($originalName !== false) {
                // Fire composers registered on the original view name
                $event = 'composing: ' . $originalName;
                if ($this->events->hasListeners($event)) {
                    $this->events->dispatch($event, [$view]);
                }
            }
        }
    }

    /**
     * Call the creator for a given view.
     *
     * Overridden to also fire creators registered on the ORIGINAL view name
     * when the view has been overridden by a plugin. This enables core code
     * to register creators that work even when plugins override templates.
     *
     * Note: Creators fire on view instantiation (earlier than composers which
     * fire on render). This is useful for setting up data before the view
     * is composed.
     *
     * @param \Illuminate\Contracts\View\View $view
     * @return void
     */
    public function callCreator(ViewContract $view)
    {
        // Call creators for the actual view name (e.g., "pluginNamespace::frontend.pages.article")
        parent::callCreator($view);

        // If this view is an override, also fire creators for the original name
        if ($this->finder instanceof FileViewFinder) {
            $viewOverrides = $this->finder->getViewOverrides();

            // Find the original name by reverse lookup
            $originalName = array_search($view->name(), $viewOverrides, true);

            if ($originalName !== false) {
                // Fire creators registered on the original view name
                $event = 'creating: ' . $originalName;
                if ($this->events->hasListeners($event)) {
                    $this->events->dispatch($event, [$view]);
                }
            }
        }
    }

    /**
     * Get the overridden view name if it exists
     */
    protected function getOverriddenView(string $view): string
    {
        // Check if FileViewFinder detected an override
        if ($this->finder instanceof FileViewFinder) {
            $override = $this->finder->getViewOverride($view);
            if ($override !== null) {
                return $override;
            }
        }

        return $view;
    }
}
