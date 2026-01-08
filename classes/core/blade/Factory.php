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
 * @brief Custom Laravel View Factory with view name resolution and caching.
 *
 * Extends Laravel's View Factory to:
 * 1. Fire View::resolveName hook for plugin template overrides
 * 2. Cache resolved names per request (avoids duplicate hook calls)
 * 3. Fire view composers for both original and aliased names
 */

namespace PKP\core\blade;

use Illuminate\Contracts\View\View as ViewContract;
use PKP\plugins\Hook;

class Factory extends \Illuminate\View\Factory
{
    /**
     * View names that have been resolved this request (with or without alias).
     * Used to avoid calling the hook multiple times for the same view.
     */
    protected array $resolved = [];

    /**
     * Mapping of aliased views: original => aliased.
     * Used for reverse lookup in callComposer/callCreator.
     */
    protected array $aliases = [];

    /**
     * Create a new view instance.
     *
     * Fires View::resolveName hook to allow plugins to override templates.
     * Results are cached per request.
     */
    public function make($view, $data = [], $mergeData = [])
    {
        $original = $this->normalizeName($view);

        // Use cached resolution if available
        if (isset($this->resolved[$original])) {
            return parent::make($this->resolved[$original], $data, $mergeData);
        }

        // Fire hook for plugin overrides
        $aliased = null;
        Hook::call('View::resolveName', [&$aliased, $original]);

        if ($aliased !== null && $aliased !== $original) {
            $this->aliases[$original] = $aliased;
            $this->resolved[$original] = $aliased;
        } else {
            $this->resolved[$original] = $original;
        }

        return parent::make($this->resolved[$original], $data, $mergeData);
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
