<?php

/**
 * @file plugins/importexport/native/filter/PKPPublicationNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicationNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a representation to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class PKPPublicationNativeXmlFilter extends NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML Publication export');
		parent::__construct($filterGroup);

		$this->setNoValidation(true);
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.PKPPublicationNativeXmlFilter';
	}

	/**
	 * Get the representation export filter group name
	 * @return string
	 */
	function getRepresentationExportFilterGroupName() {
		return 'article-galley=>native-xml';
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $entity Publication
	 * @return DOMDocument
	 */
	function &process(&$entity) {
		// Create the XML document
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();
		$rootNode = $this->createEntityNode($doc, $entity);
		$doc->appendChild($rootNode);
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

		return $doc;
	}

	//
	// Representation conversion functions
	//
	/**
	 * Create and return an entity node.
	 * @param $doc DOMDocument
	 * @param $entity Publication
	 * @return DOMElement
	 */
	function createEntityNode($doc, $entity) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the entity node
		$entityNode = $doc->createElementNS($deployment->getNamespace(), 'publication');

		$this->addIdentifiers($doc, $entityNode, $entity);
		
		$entityNode->setAttribute('locale', $entity->getData('locale'));
		
		$entityLanguages = $entity->getData('languages');
		if ($entityLanguages) {
			$entityNode->setAttribute('language', $entityLanguages);
		}
		
		if ($datePublished = $entity->getData('date_published')) {
			$entityNode->setAttribute('date_published', strftime('%Y-%m-%d', strtotime($datePublished)));
		}

		$this->addMetadata($doc, $entityNode, $entity);
		$this->addAuthors($doc, $entityNode, $entity);
		$this->addRepresentations($doc, $entityNode, $entity);

		return $entityNode;
	}

	// /**
	//  * Create and add identifier nodes to a representation node.
	//  * @param $doc DOMDocument
	//  * @param $representationNode DOMElement
	//  * @param $representation Representation
	//  */
	// function addIdentifiers($doc, $representationNode, $representation) {
	// 	$deployment = $this->getDeployment();

	// 	// Add internal ID
	// 	$representationNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', $representation->getId()));
	// 	$node->setAttribute('type', 'internal');
	// 	$node->setAttribute('advice', 'ignore');

	// 	// Add public ID
	// 	if ($pubId = $representation->getStoredPubId('publisher-id')) {
	// 		$representationNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', htmlspecialchars($pubId, ENT_COMPAT, 'UTF-8')));
	// 		$node->setAttribute('type', 'public');
	// 		$node->setAttribute('advice', 'update');
	// 	}

	// 	// Add pub IDs by plugin
	// 	$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $deployment->getContext()->getId());
	// 	foreach ($pubIdPlugins as $pubIdPlugin) {
	// 		$this->addPubIdentifier($doc, $representationNode, $representation, $pubIdPlugin);
	// 	}
	// }

	// /**
	//  * Add a single pub ID element for a given plugin to the representation.
	//  * @param $doc DOMDocument
	//  * @param $representationNode DOMElement
	//  * @param $representation Representation
	//  * @param $pubIdPlugin PubIdPlugin
	//  * @return DOMElement|null
	//  */
	// function addPubIdentifier($doc, $representationNode, $representation, $pubIdPlugin) {
	// 	$pubId = $representation->getStoredPubId($pubIdPlugin->getPubIdType());
	// 	if ($pubId) {
	// 		$deployment = $this->getDeployment();
	// 		$representationNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', htmlspecialchars($pubId, ENT_COMPAT, 'UTF-8')));
	// 		$node->setAttribute('type', $pubIdPlugin->getPubIdType());
	// 		$node->setAttribute('advice', 'update');
	// 		return $node;
	// 	}
	// 	return null;
	// }

	/**
	 * Create and add identifier nodes to a submission node.
	 * @param $doc DOMDocument
	 * @param $entityNode DOMElement
	 * @param $entity Publication
	 */
	function addIdentifiers($doc, $entityNode, $entity) {
		$deployment = $this->getDeployment();

		// Add internal ID
		$entityNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', $entity->getId()));
		$node->setAttribute('type', 'internal');
		$node->setAttribute('advice', 'ignore');

		// Add public ID
		if ($pubId = $entity->getStoredPubId('publisher-id')) {
			$entityNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', htmlspecialchars($pubId, ENT_COMPAT, 'UTF-8')));
			$node->setAttribute('type', 'public');
			$node->setAttribute('advice', 'update');
		}

		// Add pub IDs by plugin
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $deployment->getContext()->getId());
		foreach ($pubIdPlugins as $pubIdPlugin) {
			$this->addPubIdentifier($doc, $entityNode, $entity, $pubIdPlugin);
		}
	}

	/**
	 * Add a single pub ID element for a given plugin to the document.
	 * @param $doc DOMDocument
	 * @param $submissionNode DOMElement
	 * @param $submission Submission
	 * @param $pubIdPlugin PubIdPlugin
	 * @return DOMElement|null
	 */
	function addPubIdentifier($doc, $entityNode, $entity, $pubIdPlugin) {
		$pubId = $entity->getStoredPubId($pubIdPlugin->getPubIdType());
		if ($pubId) {
			$deployment = $this->getDeployment();
			$entityNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', htmlspecialchars($pubId, ENT_COMPAT, 'UTF-8')));
			$node->setAttribute('type', $pubIdPlugin->getPubIdType());
			$node->setAttribute('advice', 'update');
			return $node;
		}
		return null;
	}

	/**
	 * Add the publication metadata for a publication to its DOM element.
	 * @param $doc DOMDocument
	 * @param $entityNode DOMElement
	 * @param $entity Publication
	 */
	function addMetadata($doc, $entityNode, $entity) {
		$deployment = $this->getDeployment();
		$this->createLocalizedNodes($doc, $entityNode, 'title', $entity->getLocalizedData('title'));
		$this->createLocalizedNodes($doc, $entityNode, 'prefix', $entity->getLocalizedData('prefix'));
		$this->createLocalizedNodes($doc, $entityNode, 'subtitle', $entity->getLocalizedData('subtitle'));
		$this->createLocalizedNodes($doc, $entityNode, 'abstract', $entity->getLocalizedData('abstract'));
		$this->createLocalizedNodes($doc, $entityNode, 'coverage', $entity->getLocalizedData('coverage'));
		$this->createLocalizedNodes($doc, $entityNode, 'type', $entity->getLocalizedData('type'));
		$this->createLocalizedNodes($doc, $entityNode, 'source', $entity->getLocalizedData('source'));
		$this->createLocalizedNodes($doc, $entityNode, 'rights', $entity->getLocalizedData('rights'));
		
		if ($entity->getData('licenseUrl')) {
			$entityNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'licenseUrl', htmlspecialchars($entity->getData('licenseUrl'))));
		}

		$this->createLocalizedNodes($doc, $entityNode, 'copyrightHolder', $entity->getLocalizedData('copyrightHolder'));
		
		if ($entity->getData('copyrightYear')) {
			$entityNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'copyrightYear', intval($entity->getData('copyrightYear'))));
		}

		// add controlled vocabularies
		// get the supported locale keys
		// $supportedLocales = array_keys(AppLocale::getSupportedFormLocales());
		// $controlledVocabulariesMapping = $this->_getControlledVocabulariesMappings();
		// foreach ($controlledVocabulariesMapping as $controlledVocabulariesNodeName => $mappings) {
		// 	$dao = DAORegistry::getDAO($mappings[0]);
		// 	$getFunction = $mappings[1];
		// 	$controlledVocabularyNodeName = $mappings[2];
		// 	$controlledVocabulary = $dao->$getFunction($entity->getId(), $supportedLocales);
		// 	$this->addControlledVocabulary($doc, $entityNode, $controlledVocabulariesNodeName, $controlledVocabularyNodeName, $controlledVocabulary);
		// }
	}

	/**
	 * Add submission controlled vocabulary to its DOM element.
	 * @param $doc DOMDocument
	 * @param $submissionNode DOMElement
	 * @param $controlledVocabulariesNodeName string Parent node name
	 * @param $controlledVocabularyNodeName string Item node name
	 * @param $controlledVocabulary array Associative array (locale => array of items)
	 */
	function addControlledVocabulary($doc, $entityNode, $controlledVocabulariesNodeName, $controlledVocabularyNodeName, $controlledVocabulary) {
		$deployment = $this->getDeployment();
		$locales = array_keys($controlledVocabulary);
		foreach ($locales as $locale) {
			if (!empty($controlledVocabulary[$locale])) {
				$controlledVocabulariesNode = $doc->createElementNS($deployment->getNamespace(), $controlledVocabulariesNodeName);
				$controlledVocabulariesNode->setAttribute('locale', $locale);
				foreach ($controlledVocabulary[$locale] as $controlledVocabularyItem) {
					$controlledVocabulariesNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), $controlledVocabularyNodeName, htmlspecialchars($controlledVocabularyItem, ENT_COMPAT, 'UTF-8')));
				}

				$entityNode->appendChild($controlledVocabulariesNode);
			}
		}
	}

	/**
	 * Add the author metadata for a submission to its DOM element.
	 * @param $doc DOMDocument
	 * @param $entityNode DOMElement
	 * @param $entity Publication
	 */
	function addAuthors($doc, $entityNode, $entity) {
		$filterDao = DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
		$nativeExportFilters = $filterDao->getObjectsByGroup('author=>native-xml');
		assert(count($nativeExportFilters)==1); // Assert only a single serialization filter
		$exportFilter = array_shift($nativeExportFilters);
		$exportFilter->setDeployment($this->getDeployment());

		$authors = $entity->getData('authors');
		$authorsDoc = $exportFilter->execute($authors);
		if ($authorsDoc->documentElement instanceof DOMElement) {
			$clone = $doc->importNode($authorsDoc->documentElement, true);
			$entityNode->appendChild($clone);
		}
	}

	/**
	 * Add the representations of a submission to its DOM element.
	 * @param $doc DOMDocument
	 * @param $entityNode DOMElement
	 * @param $entity Publication
	 */
	function addRepresentations($doc, $entityNode, $entity) {
		$filterDao = DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
		$nativeExportFilters = $filterDao->getObjectsByGroup($this->getRepresentationExportFilterGroupName());
		assert(count($nativeExportFilters)==1); // Assert only a single serialization filter
		$exportFilter = array_shift($nativeExportFilters);
		$exportFilter->setDeployment($this->getDeployment());

		$representationDao = Application::getRepresentationDAO();
		$representations = $representationDao->getByPublicationId($entity->getId());
		while ($representation = $representations->next()) {
			$representationDoc = $exportFilter->execute($representation);
			$clone = $doc->importNode($representationDoc->documentElement, true);
			$entityNode->appendChild($clone);
		}
	}

	//
	// Abstract methods to be implemented by subclasses
	//
	/**
	 * Get the submission files associated with this representation
	 * @param $representation Representation
	 * @return array
	 */
	function getFiles($representation) {
		assert(false); // To be overridden by subclasses
	}
}


