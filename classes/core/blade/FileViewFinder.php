<?php

/**
 * @file classes/core/blade/FileViewFinder.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileViewFinder
 *
 * @brief Custom Laravel View Finder
 *
 * Extends Laravel's FileViewFinder for PKP-specific template resolution.
 *
 * Template override via view name aliasing is handled by Factory::make(),
 * which fires the View::alias hook before calling this finder.
 *
 * This finder uses Laravel's standard namespace resolution:
 * - Namespaced views (e.g., 'pluginName::frontend.pages.article') resolve via registered namespaces
 * - Non-namespaced views resolve via registered paths
 *
 * @see PKP\core\blade\Factory - Handles view name aliasing via View::alias hook
 * @see PKP\plugins\Plugin::_overridePluginTemplates() - Hook handler for aliasing
 */

namespace PKP\core\blade;

class FileViewFinder extends \Illuminate\View\FileViewFinder
{
    // Uses Laravel's standard implementation.
    // View name aliasing is handled by Factory::make() before find() is called.
}
