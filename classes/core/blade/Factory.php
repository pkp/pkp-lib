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
 * @brief Custom Laravel View Factory with unified template resolution.
 *
 * Central coordinator for template resolution across Laravel and Smarty:
 * 1. Fires View::resolveName hook for plugin template overrides
 * 2. Caches resolved names per request (avoids duplicate hook calls)
 * 3. Provides resolveViewName() for SmartyTemplate to use
 * 4. Fire view composers for both original and aliased names
 */

namespace PKP\core\blade;

use Illuminate\Contracts\View\View as ViewContract;
use PKP\plugins\Hook;

class Factory extends \Illuminate\View\Factory
{
    /**
     * View names that have been resolved this request (with or without alias).
     * Used to avoid calling the hook multiple times for the same view.
     * Format: ['original.view.name' => 'resolved.view.name']
     */
    protected array $resolved = [];

    /**
     * Mapping of aliased views: original => aliased.
     * Used for reverse lookup in callComposer/callCreator.
     */
    protected array $aliases = [];

    /**
     * Resolve a view name, checking for plugin overrides.
     *
     * This is the central resolution point used by both:
     * - make() for Laravel view rendering
     * - PKPTemplateResource for Smarty template loading
     *
     * Fires View::resolveName hook once per view name, caches results.
     *
     * @param string $viewName Original view name (dot notation)
     * @return string Resolved view name (may be different if plugin override exists)
     */
    public function resolveViewName(string $viewName): string
    {
        $original = $this->normalizeName($viewName);

        // Use cached resolution if available
        if (isset($this->resolved[$original])) {
            return $this->resolved[$original];
        }

        // Fire hook for plugin overrides
        // Args: $viewName (original), &$overrideViewName (set by plugins to redirect)
        $overrideViewName = null;
        Hook::call('View::resolveName', [$original, &$overrideViewName]);

        if ($overrideViewName !== null && $overrideViewName !== $original) {
            $this->aliases[$original] = $overrideViewName;
            $this->resolved[$original] = $overrideViewName;
        } else {
            $this->resolved[$original] = $original;
        }

        return $this->resolved[$original];
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
     * Call the composer for a given view.
     *
     * Fires composers for both the aliased name AND the original name,
     * so core composers work even when plugins override templates.
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
     * Clear the resolution cache.
     *
     * Used in tests that simulate context/theme switching within a single process.
     * In production, the cache naturally resets between HTTP requests.
     */
    public function clearResolvedCache(): void
    {
        $this->resolved = [];
        $this->aliases = [];
    }
}
