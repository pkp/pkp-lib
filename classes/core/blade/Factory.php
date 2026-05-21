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
 * @brief Custom Laravel View Factory with unified template resolution
 *        and a scoped fallback for unnamespaced lookups.
 *
 * Resolution order in resolveViewName():
 *   1. View::resolveName hook (theme/plugin explicit overrides)
 *   2. Scoped fallback: if unnamespaced and rendering inside a plugin
 *      scope, try {callerPluginNs}::{name} (pkp/pkp-lib#12684)
 *   3. Default FileViewFinder lookup (now only sees app + pkp paths)
 *
 * Smarty {include} and PHP view() calls share this resolver via
 * SmartyTemplate::_subTemplateRender() / fetch() and Factory::make().
 */

namespace PKP\core\blade;

use Illuminate\Contracts\View\View as ViewContract;
use InvalidArgumentException;
use PKP\plugins\Hook;

class Factory extends \Illuminate\View\Factory
{
    /**
     * Context-free resolution cache: hook-based overrides + default no-op.
     * Format: ['original.view.name' => 'resolved.view.name']
     */
    protected array $resolved = [];

    /**
     * Hook-based override aliases. Reverse-looked-up by callComposer / callCreator
     * so composers registered on the original name still fire under an override.
     * Format: ['original.view.name' => 'pluginNs::original.view.name']
     */
    protected array $aliases = [];

    /**
     * Context-keyed cache for scoped fallback resolutions. The same $original
     * can resolve differently depending on which plugin's render is on top
     * of the stack, so this cannot be merged with $resolved.
     * Format: ['callerPluginNs' => ['original.view.name' => 'callerPluginNs::original.view.name']]
     */
    protected array $scopedResolved = [];

    /**
     * Stack of view names currently rendering (top = innermost).
     * Pushed/popped by PKP\core\blade\View::renderContents().
     */
    protected array $renderingStack = [];

    /**
     * Push a view name onto the rendering stack when its render starts.
     */
    public function pushRenderingView(string $name): void
    {
        $this->renderingStack[] = $name;
    }

    /**
     * Pop the most recent view name off the rendering stack.
     */
    public function popRenderingView(): void
    {
        array_pop($this->renderingStack);
    }

    /**
     * Plugin namespace of the view currently rendering, or null when:
     *  - stack is empty (top-level render initiated outside any view)
     *  - top view is unnamespaced (a core template)
     *  - top view's namespace is 'app' or 'pkp' (also core)
     */
    public function currentRenderingNamespace(): ?string
    {
        $current = end($this->renderingStack);
        if (!is_string($current) || !str_contains($current, '::')) {
            return null;
        }
        $ns = explode('::', $current, 2)[0];
        return ($ns === 'app' || $ns === 'pkp') ? null : $ns;
    }

    /**
     * Resolve a view name, checking for plugin overrides and scoped fallback.
     *
     * @see class docblock for resolution order.
     */
    public function resolveViewName(string $viewName): string
    {
        $original = $this->normalizeName($viewName);

        // (a) View::resolveName hook (theme/plugin overrides). Cached after the
        //     first call so listeners run at most once per name per request.
        //     The hook outcome is context-free: an override applies regardless
        //     of which plugin is currently rendering, so caching is safe here.
        if (!isset($this->resolved[$original])) {
            $overrideViewName = null;
            Hook::call('View::resolveName', [$original, &$overrideViewName]);

            if ($overrideViewName !== null && $overrideViewName !== $original) {
                $this->aliases[$original] = $overrideViewName;
                $this->resolved[$original] = $overrideViewName;
            } else {
                // Hook fired with no override; mark to avoid re-firing.
                $this->resolved[$original] = $original;
            }
        }

        $cached = $this->resolved[$original];

        // If the hook set an override (cached value differs from $original),
        // it wins over both scoped fallback and the default lookup.
        if ($cached !== $original) {
            return $cached;
        }

        // (b) Scoped fallback for unnamespaced names rendered inside a plugin
        //     scope. Evaluated on every call because the resolution depends
        //     on the rendering stack -- but cached per (callerNs, name) in
        //     $scopedResolved so the FileViewFinder::find() is only called once.
        if ($scoped = $this->maybeScopedResolution($original)) {
            return $scoped;
        }

        // (c) Default: hand back the original (context-free) for FileViewFinder
        //     to resolve against the app + pkp default path list.
        return $original;
    }

    /**
     * If we're rendering inside a plugin scope and the unnamespaced $original
     * exists under that plugin's namespace, return the namespaced name.
     * Otherwise return null so resolveViewName() proceeds to hook + default.
     */
    protected function maybeScopedResolution(string $original): ?string
    {
        if (str_contains($original, '::')) {
            return null;
        }
        $callerNs = $this->currentRenderingNamespace();
        if ($callerNs === null) {
            return null;
        }
        if (isset($this->scopedResolved[$callerNs][$original])) {
            return $this->scopedResolved[$callerNs][$original];
        }

        $finder = $this->getFinder(); /** @var \Illuminate\View\FileViewFinder $finder */
        if (!isset($finder->getHints()[$callerNs])) {
            return null;
        }

        $scoped = $callerNs . '::' . $original;
        try {
            $finder->find($scoped);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->scopedResolved[$callerNs][$original] = $scoped;
    }

    /**
     * Create a new view instance.
     *
     * Uses resolveViewName() for unified resolution with caching.
     */
    public function make($view, $data = [], $mergeData = [])
    {
        $resolvedName = $this->resolveViewName($view);
        return parent::make($resolvedName, $data, $mergeData);
    }

    /**
     * Check whether a view exists, honoring plugin template overrides and
     * the scoped fallback.
     *
     * Vendor exists() asks FileViewFinder directly, bypassing the
     * View::resolveName hook AND maybeScopedResolution(). That means
     * @includeIf, @includeFirst, view()->exists(), and view()->first() can't
     * see views that exist only under a plugin's namespace hint or that a
     * theme/plugin claims via the override hook. Routing through
     * resolveViewName() closes that gap; parent::exists() then resolves the
     * namespaced name through findNamespacedView() and finds the file.
     * See pkp/pkp-lib#12684.
     */
    public function exists($view)
    {
        return parent::exists($this->resolveViewName($view));
    }

    /**
     * Override viewInstance() to return PKP\core\blade\View so the rendering
     * stack push/pop happens during render. No service-provider binding needed
     * because every View in Laravel is constructed through this method.
     *
     * @see \Illuminate\View\Factory::viewInstance()
     */
    protected function viewInstance($view, $path, $data)
    {
        return new View($this, $this->getEngineFromPath($path), $view, $path, $data);
    }

    /**
     * Call the composer for a given view.
     *
     * Fires composers for both the aliased name AND the original name,
     * so core composers work even when plugins override templates.
     *
     * NOTE: deliberately does NOT cover scoped resolutions -- those are
     * context-dependent, so any composer wanted by the plugin should be
     * registered on its namespaced view name
     * (e.g. View::composer('myplugin::components.foo', ...)).
     */
    public function callComposer(ViewContract $view)
    {
        parent::callComposer($view);

        // Fire for original name if this view was aliased
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
     */
    public function callCreator(ViewContract $view)
    {
        parent::callCreator($view);

        // Fire for original name if this view was aliased
        $original = array_search($view->name(), $this->aliases, true);
        if ($original !== false) {
            $event = 'creating: ' . $original;
            if ($this->events->hasListeners($event)) {
                $this->events->dispatch($event, [$view]);
            }
        }
    }

    /**
     * Clear ALL resolution state. Used in tests that simulate
     * context/theme switching within a single process. In production
     * the caches reset naturally between HTTP requests.
     */
    public function clearResolvedCache(): void
    {
        $this->resolved = [];
        $this->aliases = [];
        $this->scopedResolved = [];
        $this->renderingStack = [];
    }
}
