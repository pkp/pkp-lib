<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlPKPPublicationFilter.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlPKPPublicationFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of publications
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlPKPPublicationFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML publication import');
		parent::__construct($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlPKPPublicationFilter';
	}


	//
	// Implement template methods from NativeImportFilter
	//
	/**
	 * Return the plural element name
	 * @return string
	 */
	function getPluralElementName() {
		return 'publications';
	}

	/**
	 * Get the singular element name
	 * @return string
	 */
	function getSingularElementName() {
		return 'publication';
	}

	/**
	 * Handle a singular element import.
	 * @param $node DOMElement
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		$submission = $deployment->getSubmission();

		/** @var $publicationDao PublicationDAO */
		$publicationDao = DAORegistry::getDAO('PublicationDAO');
		$publication = $publicationDao->newDataObject(); /** @var $publication PKPPublication */

		$publication->setData('submissionId', $submission->getId());

		$publication->stampModified();
		$publication = $this->populateObject($publication, $node);

		$publicationLocale = $node->getAttribute('locale');
		if (empty($publicationLocale)) 
			$publicationLocale = $context->getPrimaryLocale();

		$publication->setData('locale', $publicationLocale);
		$publication->setData('version', $node->getAttribute('version'));
		$publication->setData('seq', $node->getAttribute('seq'));
		$publication->setData('accessStatus', $node->getAttribute('access_status'));
		$publication->setData('status', $node->getAttribute('status'));
		$publication->setData('primaryContactId', $node->getAttribute('primary_contact_id'));
		$publication->setData('urlPath', $node->getAttribute('url_path'));

		$publication = Services::get('publication')->add($publication, Application::get()->getRequest());
		$deployment->setPublication($publication);

		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				$this->handleChildElement($n, $publication);
			}
		}

		$publication = Services::get('publication')->edit($publication, array(), Application::get()->getRequest());

		return $publication;
	}

	/**
	 * Populate the entity object from the node
	 * @param $publication PKPPublication
	 * @param $node DOMElement
	 * @return PKPPublication
	 */
	function populateObject($publication, $node) {

		if ($datePublished = $node->getAttribute('date_published')) {
			$publication->setData('datePublished', $datePublished);
		}

		return $publication;
	}

	/**
	 * Handle an element whose parent is the publication element.
	 * @param $n DOMElement
	 * @param $publication PKPPublication
	 */
	function handleChildElement($n, $publication) {
		$setterMappings = $this->_getLocalizedPublicationFields();
		$controlledVocabulariesMappings = $this->_getControlledVocabulariesMappings();

		list($locale, $value) = $this->parseLocalizedContent($n);
		if (empty($locale)) $locale = $publication->getData('locale');

		if (in_array($n->tagName, $setterMappings)) {
			$publication->setData($n->tagName, $value, $locale);
		} elseif (isset($controlledVocabulariesMappings[$n->tagName])) {
			$controlledVocabulariesDao = $submissionKeywordDao = DAORegistry::getDAO($controlledVocabulariesMappings[$n->tagName][0]);
			$insertFunction = $controlledVocabulariesMappings[$n->tagName][1];

			$controlledVocabulary = array();
			for ($nc = $n->firstChild; $nc !== null; $nc=$nc->nextSibling) {
				if (is_a($nc, 'DOMElement')) {
					$controlledVocabulary[] = $nc->textContent;
				}
			}
			
			$controlledVocabulariesValues = array();
			$controlledVocabulariesValues[$locale] = $controlledVocabulary;

			$controlledVocabulariesDao->$insertFunction($controlledVocabulariesValues, $publication->getId(), false);

			$publicationNew = Services::get('publication')->get($publication->getId());
			$publication->setData($n->tagName, $publicationNew->getData($n->tagName));
		} else switch ($n->tagName) {
			// Otherwise, delegate to specific parsing code
			case 'id':
				$this->parseIdentifier($n, $publication);
				break;
			case 'authors':
				$this->parseAuthors($n, $publication);
				break;
			case 'citations':
				$this->parseCitations($n, $publication);
				break;
			case 'copyrightYear':
				$publication->setData('copyrightYear', $n->textContent);
				break;
			case 'licenseUrl':
				$publication->setData('licenseUrl', $n->textContent);
				break;
			default:
				$deployment = $this->getDeployment();
				$deployment->addWarning(ASSOC_TYPE_PUBLICATION, $publication->getId(), __('plugins.importexport.common.error.unknownElement', array('param' => $n->tagName)));
		}
	}

	//
	// Element parsing
	//
	/**
	 * Parse an identifier node and set up the publication object accordingly
	 * @param $element DOMElement
	 * @param $publication PKPPublication
	 */
	function parseIdentifier($element, $publication) {
		$deployment = $this->getDeployment();
		$submission = $deployment->getSubmission();

		$advice = $element->getAttribute('advice');
		switch ($element->getAttribute('type')) {
			case 'internal':
				// "update" advice not supported yet.
				assert(!$advice || $advice == 'ignore');

				if ($element->textContent == $submission->getData('currentPublicationId')) {
					$submission->setData('currentPublicationId', $publication->getId());
				}

				break;
			case 'public':
				if ($advice == 'update') {
					$publication->setData('pub-id::publisher-id', $element->textContent);
				}
				break;
			default:
				if ($advice == 'update') {
					$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $deployment->getContext()->getId());
					$publication->setData('pub-id::'.$element->getAttribute('type'), $element->textContent);
				}
		}
	}

	/**
	 * Parse an authors element
	 * @param $node DOMElement
	 * @param $publication PKPPublication
	 */
	function parseAuthors($node, $publication) {
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				assert($n->tagName == 'author');
				$this->parseAuthor($n, $publication);
			}
		}
	}

	/**
	 * Parse an author and add it to the submission.
	 * @param $n DOMElement
	 * @param $publication Publication
	 */
	function parseAuthor($n, $publication) {
		$filterDao = DAORegistry::getDAO('FilterDAO'); /** @var $filterDao FilterDAO */
		$importFilters = $filterDao->getObjectsByGroup('native-xml=>author');
		assert(count($importFilters)==1); // Assert only a single unserialization filter
		$importFilter = array_shift($importFilters);
		$importFilter->setDeployment($this->getDeployment());
		$authorDoc = new DOMDocument();
		$authorDoc->appendChild($authorDoc->importNode($n, true));
		return $importFilter->execute($authorDoc);
	}

	/**
	 * Parse a publication citation and add it to the publication.
	 * @param $n DOMElement
	 * @param $publication PKPPublication
	 */
	function parseCitations($n, $publication) {
		$publicationId = $publication->getId();
		$citationsString = $n->textContent;
		$citationDao = DAORegistry::getDAO('CitationDAO'); /** @var $citationDao CitationDAO */
		$citationDao->importCitations($publicationId, $citationsString);
	}

	//
	// Helper functions
	//
	/**
	 * Get node name to setter function mapping for localized data.
	 * @return array
	 */
	function _getLocalizedPublicationFields() {
		return array(
			'title',
			'prefix',
			'subtitle',
			'abstract',
			'coverage',
			'type',
			'source',
			'rights',
			'copyrightHolder',
		);
	}

	/**
	 * Get node name to DAO and insert function mapping.
	 * @return array
	 */
	function _getControlledVocabulariesMappings() {
		return array(
			'keywords' => array('SubmissionKeywordDAO', 'insertKeywords'),
			'agencies' => array('SubmissionAgencyDAO', 'insertAgencies'),
			'disciplines' => array('SubmissionDisciplineDAO', 'insertDisciplines'),
			'subjects' => array('SubmissionSubjectDAO', 'insertSubjects'),
		);
	}

	/**
	 * Get the representation export filter group name
	 * @return string
	 */
	function getRepresentationExportFilterGroupName() {
		assert(false); // Subclasses must override
	}

	/**
	 * Get the import filter for a given element.
	 * @param $elementName string Name of XML element
	 * @return Filter
	 */
	function getImportFilter($elementName) {
		assert(false); // Subclasses should override
	}
}


