<?php

/**
 * @file classes/plugins/MetadataPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for metadata plugins
 */

namespace PKP\plugins;

abstract class MetadataPlugin extends Plugin
{
    //
    // Override public methods from Plugin
    //
    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        $this->addLocaleData();
        return true;
    }

    /**
     * Get a unique id for this metadata format
     *
     * @param string $format The format to check for support.
     *
     * @return string
     */
    abstract public function supportsFormat($format);

    /**
     * Instantiate and return the schema object for this metadata format
     *
     * @param string $format The format to return the schema object for in case
     *  the plugin supports multiple formats.
     */
    abstract public function getSchemaObject($format);
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\MetadataPlugin', '\MetadataPlugin');
}
