<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlPublicationFilter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlPublicationFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Class that converts a Native XML document to a set of publications.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeXmlPKPPublicationFilter');

class NativeXmlPublicationFilter extends NativeXmlPKPPublicationFilter {
	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'plugins.importexport.native.filter.NativeXmlPublicationFilter';
	}

	/**
	 * Handle an preprint import.
	 * The preprint must have a valid section in order to be imported
	 * @param $node DOMElement
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$sectionAbbrev = $node->getAttribute('section_ref');
		if ($sectionAbbrev !== '') {
			$sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
			$section = $sectionDao->getByAbbrev($sectionAbbrev, $context->getId());
			if (!$section) {
				$deployment->addError(ASSOC_TYPE_SUBMISSION, NULL, __('plugins.importexport.native.error.unknownSection', array('param' => $sectionAbbrev)));
			} else {
				return parent::handleElement($node);
			}
		}
	}

	/**
	 * Populate the submission object from the node, checking first for a valid section and published_date relationship
	 * @param $publication Publication
	 * @param $node DOMElement
	 * @return Publication
	 */
	function populateObject($publication, $node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		$sectionAbbrev = $node->getAttribute('section_ref');
		if ($sectionAbbrev !== '') {
			$sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
			$section = $sectionDao->getByAbbrev($sectionAbbrev, $context->getId());
			if (!$section) {
				$deployment->addError(ASSOC_TYPE_PUBLICATION, $publication->getId(), __('plugins.importexport.native.error.unknownSection', array('param' => $sectionAbbrev)));
			} else {
				$publication->setData('sectionId', $section->getId());
			}
		}

		return parent::populateObject($publication, $node);
	}

	/**
	 * Handle an element whose parent is the submission element.
	 * @param $n DOMElement
	 * @param $publication Publication
	 */
	function handleChildElement($n, $publication) {
		switch ($n->tagName) {
			case 'preprint_galley':
				$this->parsePreprintGalley($n, $publication);
				break;
			case 'pages':
				$publication->setData('pages', $n->textContent);
				break;
			case 'covers':
				import('lib.pkp.plugins.importexport.native.filter.PKPNativeFilterHelper');
				$nativeFilterHelper = new PKPNativeFilterHelper();
				$nativeFilterHelper->parsePublicationCovers($this, $n, $publication);
				break;
			default:
				parent::handleChildElement($n, $publication);
		}
	}

	/**
	 * Get the import filter for a given element.
	 * @param $elementName string Name of XML element
	 * @return Filter
	 */
	function getImportFilter($elementName) {
		$deployment = $this->getDeployment();
		$submission = $deployment->getSubmission();
		switch ($elementName) {
			case 'preprint_galley':
				$importClass='ArticleGalley';
				break;
			default:
				$importClass=null; // Suppress scrutinizer warn
				$deployment->addWarning(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', array('param' => $elementName)));
		}
		// Caps on class name for consistency with imports, whose filter
		// group names are generated implicitly.
		$currentFilter = PKPImportExportFilter::getFilter('native-xml=>' . $importClass, $deployment);
		return $currentFilter;
	}

	/**
	 * Parse an preprint galley and add it to the publication.
	 * @param $n DOMElement
	 * @param $publication Publication
	 */
	function parsePreprintGalley($n, $publication) {
		return $this->importWithXMLNode($n);
	}
}
