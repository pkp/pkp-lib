<?php

/**
 * @file classes/template/PKPTemplateResource.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTemplateResource
 *
 * @ingroup template
 *
 * @brief Representation for a PKP template resource (template directory).
 */

namespace PKP\template;

use APP\core\Application;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\View;
use PKP\core\blade\FileViewFinder;
use PKP\plugins\Hook;

class PKPTemplateResource extends \Smarty_Resource_Custom
{
    /** @var array|string Template path or list of paths */
    protected $_templateDir;

    /**
     * Constructor
     *
     * @param string|array $templateDir Template directory
     */
    public function __construct($templateDir)
    {
        if (is_string($templateDir)) {
            $this->_templateDir = [$templateDir];
        } else {
            $this->_templateDir = $templateDir;
        }
    }

    /**
     * Resource function to get a template.
     *
     * @param string $name Template name
     * @param string $source Reference to variable receiving fetched Smarty source
     * @param int|bool $mtime Modification time
     *
     * @return bool
     */
    public function fetch($name, &$source, &$mtime)
    {
        $filename = $this->_getFilename($name);

        /*
         * SMARTY → BLADE OVERRIDE BRIDGE
         *
         * Enables plugins to override Smarty templates with Blade templates during migration.
         *
         * HOW IT WORKS:
         * 1. Hook returns Blade view path (namespace notation OR absolute path)
         * 2. Blade renders to HTML string
         * 3. Smarty receives HTML as $source and treats it as literal content
         *
         * PATH FORMAT HANDLING:
         * - Namespace notation (e.g., "pluginNamespace::frontend.pages.article"):
         *   Uses view($filename, ...) which goes through FileViewFinder
         *   This is the PRIMARY format from Blade context overrides
         *
         * - Absolute path (e.g., "/path/to/plugin/article.blade"):
         *   Uses View::file($filename, ...) which bypasses FileViewFinder
         *   This is used for Smarty context overrides pointing to Blade files
         *
         * HOOK CALL PATTERNS:
         * - Each unique template name fires the hook once (cached after first call)
         * - Parent + child templates = 2 hook calls (expected, different templates)
         *
         * NOTES:
         * - $mtime = time() is intentional - Blade manages its own compilation cache
         * - strpos($filename, '::') detects namespace notation vs absolute path
         */
        if ($this->isBladeViewPath($filename)) {
            $mtime = time();
            $templateManager = TemplateManager::getManager(Application::get()->getRequest());

            if (strpos($filename, '::') !== false) {
                // SMARTY → BLADE OVERRIDE: Register view override mapping
                // This enables Factory::callComposer() to fire composers registered
                // on the original (non-namespaced) view name
                $this->registerViewOverrideMapping($name, $filename);

                $source = view($filename, $templateManager->getTemplateVars())->render();
            } else {
                $source = View::file($filename, $templateManager->getTemplateVars())->render();
            }

            return true;
        }

        $mtime = filemtime($filename);
        if ($mtime === false) {
            return false;
        }

        $source = file_get_contents($filename);
        return ($source !== false);
    }

    /**
     * Get the timestamp for the specified template.
     *
     * @param string $name Template name
     *
     * @return int|boolean
     */
    protected function fetchTimestamp($name)
    {
        $filename = $this->_getFilename($name);

        // Check if this is a Blade view path (namespace notation)
        if ($this->isBladeViewPath($filename)) {
            return time(); // Return current time for Blade views (always fresh)
        }

        return filemtime($filename);
    }

    /**
     * Get the complete template path and filename.
     *
     * @return string|null
     *
     * @hook TemplateResource::getFilename [[&$filePath, $template]]
     */
    protected function _getFilename($template)
    {
        $filePath = null;
        foreach ($this->_templateDir as $path) {
            $filePath = "{$path}/{$template}";
            if (file_exists($filePath)) {
                break;
            }
        }
        Hook::call('TemplateResource::getFilename', [&$filePath, $template]);
        return $filePath;
    }

    /**
     * Detect if path is a Blade view (namespace notation or absolute .blade file)
     *
     * OPTIMIZATION: Early returns for obvious Smarty paths prevent unnecessary
     * view()->exists() calls, which would trigger FileViewFinder and fire
     * the TemplateResource::getFilename hook redundantly.
     *
     * Detection priority:
     * 1. .tpl extension → Definitely Smarty
     * 2. Single colon without double colon → Smarty resource notation
     * 3. .blade extension with file_exists → Blade absolute path
     * 4. Double colon (::) → Blade namespace notation
     * 5. Fall back to view()->exists() for ambiguous cases
     */
    private function isBladeViewPath(string $path): bool
    {
        static $cache = [];

        if (isset($cache[$path])) {
            return $cache[$path];
        }

        // OPTIMIZATION: Skip view()->exists() for obvious Smarty paths
        // 1. Paths ending with .tpl are Smarty templates
        if (str_ends_with($path, '.tpl')) {
            $cache[$path] = false;
            return false;
        }

        // 2. Smarty resource notation uses single colon (plugins-...:path.tpl)
        //    while Blade namespace uses double colon (namespace::view)
        //    Check for single colon WITHOUT double colon
        if (str_contains($path, ':') && !str_contains($path, '::')) {
            $cache[$path] = false;
            return false;
        }

        // Check if it's an absolute path to a .blade file
        if (pathinfo($path, PATHINFO_EXTENSION) === 'blade' && file_exists($path)) {
            $cache[$path] = true;
            return true;
        }

        // Check for namespace notation (::)
        if (str_contains($path, '::')) {
            $cache[$path] = true;
            return true;
        }

        // Last resort: Ask Laravel's view system (may trigger FileViewFinder)
        // This only runs for edge cases where path format is ambiguous
        $result = view()->exists($path);
        $cache[$path] = $result;

        return $result;
    }

    /**
     * Register view override mapping for Smarty → Blade overrides
     *
     * When Smarty context overrides to a Blade template, the view name passed
     * to Factory::make() is already namespaced. This means the normal mapping
     * flow in FileViewFinder::find() doesn't occur, and Factory::callComposer()
     * cannot fire composers registered on the original (non-namespaced) view name.
     *
     * This method manually populates the viewOverrides mapping by:
     * 1. Converting Smarty path to equivalent Blade view name
     * 2. Registering the mapping in FileViewFinder
     *
     * Example:
     *   $smartyPath: 'frontend/pages/article.tpl'
     *   $bladeNamespace: 'defaultthemeplugin::frontend.pages.article'
     *   Derived original: 'frontend.pages.article'
     *   Mapping stored: ['frontend.pages.article' => 'defaultthemeplugin::...']
     *
     * @param string $smartyPath Smarty template path (e.g., 'frontend/pages/article.tpl')
     * @param string $bladeNamespace Namespaced Blade view (e.g., 'pluginNamespace::frontend.pages.article')
     */
    private function registerViewOverrideMapping(string $smartyPath, string $bladeNamespace): void
    {
        $finder = app('view')->getFinder();

        if (!$finder instanceof FileViewFinder) {
            return;
        }

        // Convert Smarty path to equivalent Blade view name
        // 'frontend/pages/article.tpl' → 'frontend.pages.article'
        // 'templates/frontend/pages/article.tpl' → 'frontend.pages.article'
        $bladeName = $smartyPath;

        if (str_starts_with($bladeName, 'templates/')) {
            $bladeName = substr($bladeName, strlen('templates/'));
        }

        if (str_ends_with($bladeName, '.tpl')) {
            $bladeName = substr($bladeName, 0, -4);
        }

        $bladeName = str_replace('/', '.', $bladeName);

        // Register the mapping so callComposer() can do reverse lookup
        $finder->addViewOverride($bladeName, $bladeNamespace);
    }
}
