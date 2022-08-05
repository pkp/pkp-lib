<?php

/**
 * @file plugins/oaiMetadataFormats/dc/PKPOAIMetadataFormatPlugin_DC.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPOAIMetadataFormatPlugin_DC
 *
 * @see OAI
 *
 * @brief dc metadata format plugin for OAI.
 */

namespace PKP\plugins\oaiMetadataFormats\dc;

class PKPOAIMetadataFormatPlugin_DC extends \PKP\plugins\OAIMetadataFormatPlugin
{
    /**
     * Get the name of this plugin. The name must be unique within
     * its category.
     *
     * @return string name of plugin
     */
    public function getName()
    {
        return 'OAIMetadataFormatPlugin_DC';
    }

    public function getDisplayName()
    {
        return __('plugins.oaiMetadata.dc.displayName');
    }

    public function getDescription()
    {
        return __('plugins.oaiMetadata.dc.description');
    }

    public function getFormatClass()
    {
        return '\APP\plugins\oaiMetadataFormats\dc\OAIMetadataFormat_DC';
    }

    public static function getMetadataPrefix()
    {
        return 'oai_dc';
    }

    public static function getSchema()
    {
        return 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd';
    }

    public static function getNamespace()
    {
        return 'http://www.openarchives.org/OAI/2.0/oai_dc/';
    }
}
