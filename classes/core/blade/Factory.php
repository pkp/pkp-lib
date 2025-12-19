<?php

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

        // Create view instance with the TRANSFORMED name if overridden
        // This ensures view composers registered on namespace patterns will match
        return $this->viewInstance($actualView, $path, $data);
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
}
