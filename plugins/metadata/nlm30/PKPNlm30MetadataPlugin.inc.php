<?php
/**
 * @defgroup plugins_metadata_nlm30 NLM 3.0 Metadata Plugin
 */

/**
 * @file plugins/metadata/nlm30/PKPNlm30MetadataPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNlm30MetadataPlugin
 * @ingroup plugins_metadata_nlm30
 *
 * @brief Abstract base class for NLM 3.0 metadata plugins
 */


import('lib.pkp.classes.plugins.MetadataPlugin');

class PKPNlm30MetadataPlugin extends MetadataPlugin {

	//
	// Override protected template methods from Plugin
	//
	/**
	 * @copydoc Plugin::getName()
	 */
	function getName() {
		return 'Nlm30MetadataPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.metadata.nlm30.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.metadata.nlm30.description');
	}

	/**
	 * @copydoc MetadataPlugin::supportsFormat()
	 */
	public function supportsFormat($format) {
		return $format === 'nlm30citation' || $format === 'nlm30name';
	}

	/**
	 * @copydoc MetadataPlugin::getSchemaObject()
	 */
	public function getSchemaObject($format) {
		assert($this->supportsFormat($format));
		if ($format === 'nlm30citation') {
			import('lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema');
			return new Nlm30CitationSchema();
		} elseif ($format === 'nlm30name') {
			import('lib.pkp.plugins.metadata.nlm30.schema.Nlm30NameSchema');
			return new Nlm30NameSchema();
		}
		assert(false);
	}
}

?>
