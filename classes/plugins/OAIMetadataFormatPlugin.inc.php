<?php

/**
 * @file lib/pkp/classes/plugins/OAIMetadataFormatPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIMetadataFormatPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for OAI Metadata format plugins
 */

import('lib.pkp.classes.plugins.Plugin');
import('lib.pkp.classes.oai.OAIStruct');

abstract class OAIMetadataFormatPlugin extends Plugin {

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		if (!parent::register($category, $path, $mainContextId)) return false;
		$this->addLocaleData();
		if ($this->getEnabled()) HookRegistry::register('OAI::metadataFormats', array($this, 'callback_formatRequest'));
		return true;
	}

	/**
	 * Get the metadata prefix for this plugin's format.
	 */
	static function getMetadataPrefix() {
		assert(false); // Should always be overridden
	}

	static function getSchema() {
		return '';
	}

	static function getNamespace() {
		return '';
	}

	/**
	 * Get a hold of the class that does the formatting.
	 */
	abstract function getFormatClass();

	function callback_formatRequest($hookName, $args) {
		$namesOnly = $args[0];
		$identifier = $args[1];
		$formats =& $args[2];

		if ($namesOnly) {
			$formats = array_merge($formats,array($this->getMetadataPrefix()));
		} else {
			$formatClass = $this->getFormatClass();
			$formats = array_merge(
				$formats,
				array($this->getMetadataPrefix() => new $formatClass($this->getMetadataPrefix(), $this->getSchema(), $this->getNamespace()))
			);
		}
		return false;
	}
}


