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
	 * Get a set of GalleryPlugin objects describing the available
	 * compatible plugins in their newest versions.
	 * @param $application PKPApplication
	 * @return array GalleryPlugin objects
	 */
	function getNewestCompatible($application) {
		$doc = $this->_getDocument();
		$plugins = array();
		foreach ($doc->getElementsByTagName('plugin') as $element) {
			$plugin = $this->_compatibleFromElement($element, $application);
			// May be null if no compatible version exists.
			if ($plugin) $plugins[] = $plugin;
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
	 * Build a GalleryPlugin from a DOM element, using the newest compatible
	 * release with the supplied Application.
	 * @param $element DOMElement
	 * @param $application Application
	 * @return GalleryPlugin or null, if no compatible plugin was available
	 */
	protected function _compatibleFromElement($element, $application) {
		$plugin = $this->newDataObject();
		$plugin->setCategory($element->getAttribute('category'));
		$plugin->setProduct($element->getAttribute('product'));
		$doc = $element->ownerDocument;
		$foundRelease = false;
		for ($n = $element->firstChild; $n; $n=$n->nextSibling) {
			if (!is_a($n, 'DOMElement')) continue;
			switch ($n->tagName) {
				case 'name':
					$plugin->setName($n->nodeValue, $n->getAttribute('locale'));
					break;
				case 'homepage':
					$plugin->setHomepage($n->nodeValue);
					break;
				case 'description':
					$plugin->setDescription($n->nodeValue, $n->getAttribute('locale'));
					break;
				case 'installation':
					$plugin->setInstallationInstructions($n->nodeValue, $n->getAttribute('locale'));
					break;
				case 'summary':
					$plugin->setSummary($n->nodeValue, $n->getAttribute('locale'));
					break;
				case 'maintainer':
					$this->_handleMaintainer($n, $plugin);
					break;
				case 'release':
					// If a compatible release couldn't be
					// found, return null.
					if ($this->_handleRelease($n, $plugin, $application)) $foundRelease = true;
					break;
				default:
					// Not erroring out here so that future
					// additions won't break old releases.
			}
		}
		if (!$foundRelease) {
			// No compatible release was found.
			return null;
		}
		return $plugin;
	}

	/**
	 * Handle a maintainer element
	 * @param $maintainerElement DOMElement
	 * @param $plugin GalleryPlugin
	 */
	function _handleMaintainer($element, $plugin) {
		for ($n = $element->firstChild; $n; $n=$n->nextSibling) {
			if (!is_a($n, 'DOMElement')) continue;
			switch ($n->tagName) {
				case 'name':
					$plugin->setContactName($n->nodeValue);
					break;
				case 'institution':
					$plugin->setContactInstitutionName($n->nodeValue);
					break;
				case 'email':
					$plugin->setContactEmail($n->nodeValue);
					break;
				default:
					// Not erroring out here so that future
					// additions won't break old releases.
			}
		}
	}

	/**
	 * Handle a release element
	 * @param $maintainerElement DOMElement
	 * @param $plugin GalleryPlugin
	 * @param $application PKPApplication
	 */
	function _handleRelease($element, $plugin, $application) {
		$release = array(
			'date' => strtotime($element->getAttribute('date')),
			'version' => $element->getAttribute('version')
		);

		$compatible = false;
		for ($n = $element->firstChild; $n; $n=$n->nextSibling) {
			if (!is_a($n, 'DOMElement')) continue;
			switch ($n->tagName) {
				case 'description':
					$release[$n->tagName][$n->getAttribute('locale')] = $n->nodeValue;
					break;
				case 'package':
					$release['package'] = $n->nodeValue;
					break;
				case 'compatibility':
					// If a compatible release couldn't be
					// found, return null.
					if ($this->_handleCompatibility($n, $plugin, $application)) {
						$compatible = true;
					}
					break;
				case 'certification':
					$release[$n->tagName][] = $n->getAttribute('type');
					break;
				default:
					// Not erroring out here so that future
					// additions won't break old releases.
			}
		}

		if ($compatible && (!$plugin->getDate() || $plugin->getDate() >= $release['date'])) {
			// This release is newer than the one found earlier, or
			// this is the first compatible release we've found.
			$plugin->setDate($release['date']);
			$plugin->setVersion($release['version']);
			$plugin->setReleaseDescription($release['description']);
			$plugin->setReleaseCertifications($release['certification']);
			$plugin->setReleasePackage($release['package']);
			return true;
		}
		return false;
	}

	/**
	 * Handle a compatibility element, fishing out the most recent statement
	 * of compatibility.
	 * @param $maintainerElement DOMElement
	 * @param $plugin GalleryPlugin
	 * @param $application PKPApplication
	 * @return boolean True iff a compatibility statement matched this app
	 */
	function _handleCompatibility($element, $plugin, $application) {
		// Check that the compatibility statement refers to this app
		if ($element->getAttribute('application')!=$application->getName()) return false;

		for ($n = $element->firstChild; $n; $n=$n->nextSibling) {
			if (!is_a($n, 'DOMElement')) continue;
			switch ($n->tagName) {
				case 'version':
					$installedVersion = $application->getCurrentVersion();
					if ($installedVersion->compare($n->nodeValue)==0) {
						// Compatibility was determined.
						return true;
					}
					break;
			}
		}

		// No applicable compatibility statement found.
		return false;
	}
}

?>
