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
         * This following `if` block handle the case where : Plugin can override a core child smarty
         * template with a plugin level child balde template only without the need to override the
         * core parent smarty template from the plugin . 
         * 
         * For example : core article.tpl include article_details.tpl and plugin only have the 
         * override as article_details.blade but no override of core parent article.tpl. Plugin 
         * still should be able to override the core article_details.tpl with the plugin level
         * article_details.blade without overriding the core article.tpl.
         */
        if ($this->isBladeViewPath($filename)) {
            $mtime = time();
            $templateManager = TemplateManager::getManager(Application::get()->getRequest());
            $source = view($filename, $templateManager->getTemplateVars())->render();
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
        
        $result = strpos($path, '::') !== false || view()->exists($path);
        $cache[$path] = $result;
        
        return $result;
    }
}
