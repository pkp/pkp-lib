<?php

/**
 * @file classes/template/PKPTemplateResource.inc.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTemplateResource
 * @ingroup template
 *
 * @brief Representation for a PKP template resource (template directory).
 */

namespace PKP\template;

use PKP\plugins\HookRegistry;

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
        return filemtime($this->_getFilename($name));
    }

    /**
     * Get the complete template path and filename.
     *
     * @return string|null
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
        HookRegistry::call('TemplateResource::getFilename', [&$filePath, $template]);
        return $filePath;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\template\PKPTemplateResource', '\PKPTemplateResource');
}
