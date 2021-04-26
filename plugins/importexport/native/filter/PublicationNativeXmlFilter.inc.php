<?php

/**
 * @file plugins/importexport/native/filter/PublicationNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Class that converts a Publication to a Native XML document.
 */

import('lib.pkp.plugins.importexport.native.filter.PKPPublicationNativeXmlFilter');

class PublicationNativeXmlFilter extends PKPPublicationNativeXmlFilter {
	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'plugins.importexport.native.filter.PublicationNativeXmlFilter';
	}


	//
	// Implement abstract methods from SubmissionNativeXmlFilter
	//
	/**
	 * Get the representation export filter group name
	 * @return string
	 */
	function getRepresentationExportFilterGroupName() {
		return 'preprint-galley=>native-xml';
	}

	//
	// Publication conversion functions
	//
	/**
	 * Create and return a publication node.
	 * @param $doc DOMDocument
	 * @param $entity Publication
	 * @return DOMElement
	 */
	function createEntityNode($doc, $entity) {
		$deployment = $this->getDeployment();
		$entityNode = parent::createEntityNode($doc, $entity);

		// Add the series, if one is designated.
		if ($sectionId = $entity->getData('sectionId')) {
			$sectionDao = DAORegistry::getDAO('SectionDAO'); /** @var $sectionDao SectionDAO */
			$section = $sectionDao->getById($sectionId);
			assert(isset($section));
			$entityNode->setAttribute('section_ref', $section->getLocalizedAbbrev());
		}

		$pages = $entity->getData('pages');
		if (!empty($pages)) $entityNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'pages', htmlspecialchars($pages, ENT_COMPAT, 'UTF-8')));

		// cover images
		import('lib.pkp.plugins.importexport.native.filter.PKPNativeFilterHelper');
		$nativeFilterHelper = new PKPNativeFilterHelper();
		$coversNode = $nativeFilterHelper->createPublicationCoversNode($this, $doc, $entity);
		if ($coversNode) $entityNode->appendChild($coversNode);

		return $entityNode;
	}
}
