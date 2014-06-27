<?php

/**
 * @file classes/plugins/PluginGalleryDAO.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PluginGalleryDAO
 * @ingroup plugins
 * @see DAO
 *
 * @brief Operations for retrieving content from the PKP plugin gallery.
 */

import('lib.pkp.classes.plugins.GalleryPlugin');

define('PLUGIN_GALLERY_XML_URL', 'http://localhost/git/ojs/plugins.xml');

class PluginGalleryDAO extends DAO {
	/**
	 * Constructor
	 */
	function PluginGalleryDAO() {
		parent::DAO();
	}

	/**
	 * Get an array of DOM elements describing the available plugins.
	 * @return array Array of DOM elements
	 */
	function get() {
		$doc = $this->_getDocument();
		$plugins = array();
		foreach ($doc->getElementsByTagName('plugin') as $element) {
			$plugins[] = $this->_fromElement($element);
		}
		return $plugins;
	}

	/**
	 * Get the DOM document for the plugin gallery.
	 * @return DOMDocument
	 */
	private function _getDocument() {
		$doc = new DOMDocument('1.0');
		$doc->load(PLUGIN_GALLERY_XML_URL);
		return $doc;
	}

	/**
	 * Construct a new data object.
	 * @return GalleryPlugin
	 */
	function newDataObject() {
		return new GalleryPlugin();
	}

	/**
	 * Build a GalleryPlugin from a DOM element.
	 * @param $element DOMElement
	 * @return GalleryPlugin
	 */
	protected function _fromElement($element) {
		$plugin = $this->newDataObject();
		$doc = $element->ownerDocument;
		foreach ($doc->getElementsByTagName('name') as $element) {
			$plugin->setName($element->nodeValue, $element->getAttribute('locale'));
		}
		foreach ($doc->getElementsByTagName('description') as $element) {
			$plugin->setDescription($element->nodeValue, $element->getAttribute('locale'));
		}
		return $plugin;
	}
}

?>
