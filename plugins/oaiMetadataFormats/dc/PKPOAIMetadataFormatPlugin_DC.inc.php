<?php

/**
 * @file plugins/oaiMetadata/dc/PKPOAIMetadataFormatPlugin_DC.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPOAIMetadataFormatPlugin_DC
 * @see OAI
 *
 * @brief dc metadata format plugin for OAI.
 */

import('lib.pkp.classes.plugins.OAIMetadataFormatPlugin');

class PKPOAIMetadataFormatPlugin_DC extends OAIMetadataFormatPlugin {
	/**
	 * Constructor
	 */
	function PKPOAIMetadataFormatPlugin_DC() {
		parent::OAIMetadataFormatPlugin();
	}
	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'OAIMetadataFormatPlugin_DC';
	}

	function getDisplayName() {
		return __('plugins.oaiMetadata.dc.displayName');
	}

	function getDescription() {
		return __('plugins.oaiMetadata.dc.description');
	}

	function getFormatClass() {
		return 'OAIMetadataFormat_DC';
	}

	function getMetadataPrefix() {
		return 'oai_dc';
	}

	function getSchema() {
		return 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd';
	}

	function getNamespace() {
		return 'http://www.openarchives.org/OAI/2.0/oai_dc/';
	}
}

?>
