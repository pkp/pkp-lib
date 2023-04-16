<?php

/**
 * @file lib/pkp/classes/plugins/OAIMetadataFormatPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIMetadataFormatPlugin
 *
 * @ingroup plugins
 *
 * @brief Abstract class for OAI Metadata format plugins
 */

namespace PKP\plugins;

abstract class OAIMetadataFormatPlugin extends Plugin
{
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
        if ($this->getEnabled()) {
            Hook::add('OAI::metadataFormats', [$this, 'callback_formatRequest']);
        }
        return true;
    }

    /**
     * Get the metadata prefix for this plugin's format.
     */
    public static function getMetadataPrefix()
    {
        assert(false); // Should always be overridden
    }

    public static function getSchema()
    {
        return '';
    }

    public static function getNamespace()
    {
        return '';
    }

    /**
     * Get a hold of the class that does the formatting.
     */
    abstract public function getFormatClass();

    public function callback_formatRequest($hookName, $args)
    {
        $namesOnly = $args[0];
        $identifier = $args[1];
        $formats = & $args[2];

        if ($namesOnly) {
            $formats = array_merge($formats, [$this->getMetadataPrefix()]);
        } else {
            $formatClass = $this->getFormatClass();
            $formats = array_merge(
                $formats,
                [$this->getMetadataPrefix() => new $formatClass($this->getMetadataPrefix(), $this->getSchema(), $this->getNamespace())]
            );
        }
        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\OAIMetadataFormatPlugin', '\OAIMetadataFormatPlugin');
}
