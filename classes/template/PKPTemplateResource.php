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
use PKP\plugins\Hook;
use Throwable;

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
         * 1. Hook returns Blade view path (namespace OR absolute path)
         * 2. Blade renders to HTML string
         * 3. Smarty receives HTML as $source and treats it as literal content
         *
         * WHY IT'S NEEDED THIS WAY:
         * - Smarty's fetch() expects a source string - HTML is valid input
         * - This is the ONLY interception point for all Smarty template loads
         * - Allows plugins to override without modifying core Smarty templates
         *
         * Double Hook Call Mitigation (Edge case):
         * - Absolute paths use View::file() - bypasses FileViewFinder (no hook call)
         * - Namespace notation uses view() - requires FileViewFinder (hook needed)
         *
         * EXAMPLE FLOW:
         * Core Smarty: {include "article_details.tpl"}
         *   → Hook returns: "/absolute/path/to/plugin/article_details.blade"
         *   → View::file() renders directly (no second hook)
         *   → Smarty receives HTML (no Smarty compilation)
         *
         * NOTES:
         * - $mtime = time() is intentional - Blade manages its own compilation cache
         * - View::file() vs view() selection prevents duplicate hook calls
         */
        if ($this->isBladeViewPath($filename)) {
            $mtime = time();
            $templateManager = TemplateManager::getManager(Application::get()->getRequest());

            $source = strpos($filename, '::') !== false
                ? view($filename, $templateManager->getTemplateVars())->render()
                : View::file($filename, $templateManager->getTemplateVars())->render();

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

    /*
     * Detect Blade view namespace
     */
    private function isBladeViewPath(string $path): bool
    {
        static $cache = [];
    
        if (isset($cache[$path])) {
            return $cache[$path];
        }

        // Check if it's an absolute path to a .blade file
        if (pathinfo($path, PATHINFO_EXTENSION) === 'blade' && file_exists($path)) {
            $cache[$path] = true;
            return true;
        }
        
        // Check for namespace notation or the namespaced view exists
        $result = strpos($path, '::') !== false || view()->exists($path);
        $cache[$path] = $result;
        
        return $result;
    }
}
