<?php

/**
 * @file classes/citation/NlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaFilter
 * @ingroup classes_citation
 *
 * @brief Abstract base class for all filters that transform
 *  NLM citation metadata descriptions.
 */

define('NLM_CITATION_FILTER_PARSE', 0x1);
define('NLM_CITATION_FILTER_LOOKUP', 0x2);

import('lib.pkp.classes.filter.Filter');
import('lib.pkp.classes.filter.BooleanFilterSetting');

import('lib.pkp.classes.metadata.MetadataDescription');
import('lib.pkp.classes.metadata.nlm.NlmCitationSchema');
import('lib.pkp.classes.metadata.nlm.NlmNameSchema');
import('lib.pkp.classes.metadata.nlm.PersonStringNlmNameSchemaFilter');
import('lib.pkp.classes.metadata.DateStringNormalizerFilter');

import('lib.pkp.classes.webservice.XmlWebService');

import('lib.pkp.classes.xml.XMLHelper');
import('lib.pkp.classes.xslt.XSLTransformationFilter');

class NlmCitationSchemaFilter extends Filter {
	/** @var array */
	var $_supportedPublicationTypes;

	/** @var array */
	var $_supportedTransformation;

	/**
	 * Constructor
	 *
	 * @param $filterType integer whether this is a parser
	 *  or a lookup filter
	 * @param $supportedPublicationTypes array
	 */
	function NlmCitationSchemaFilter($filterType = null, $supportedPublicationTypes = array()) {
		// All NLM citation filters require XSL functionality
		// that is only present in PHP5.
		$this->setData('phpVersionMin', '5.0.0');

		$this->_supportedPublicationTypes = $supportedPublicationTypes;
		switch($filterType) {
			case NLM_CITATION_FILTER_PARSE:
				$this->_supportedTransformation = array(
					'primitive::string',
					'metadata::lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)'
				);
				break;

			case NLM_CITATION_FILTER_LOOKUP:
				$this->_supportedTransformation = array(
					'metadata::lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)',
					'metadata::lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)'
				);
				break;
		}

		// Instantiate the "isOptional" setting
		// which is common to all NLM citation filters.
		// It contains the information whether a filter
		// will be used automatically within a given context
		// or whether the user will have to use it
		// explicitly (e.g. when parsing citations for
		// an article, conference paper or monograph).
		$isOptional = new BooleanFilterSetting('isOptional',
				'metadata.filters.settings.isOptional.displayName',
				'metadata.filters.settings.isOptional.validationMessage');
		$this->addSetting($isOptional);

		parent::Filter();
	}

	//
	// Setters and Getters
	//
	/**
	 * Get the supported publication types
	 * @return array
	 */
	function getSupportedPublicationTypes() {
		return $this->_supportedPublicationTypes;
	}

	/**
	 * Whether this filter is optional within its
	 * context (journal, conference, press, etc.)
	 * @return boolean
	 */
	function getIsOptional() {
		return $this->getData('isOptional');
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getSupportedTransformation()
	 */
	function getSupportedTransformation() {
		return $this->_supportedTransformation;
	}

	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @param $output mixed
	 * @param $fromString boolean true if the filter accepts a string as input.
	 * @param $toString boolean true if the filter produces a string as output.
	 * @return boolean
	 */
	function supports(&$input, &$output) {
		// Do the normal type check first.
		if (!parent::supports($input, $output)) return false;

		// Additional checks that cannot be done via type checks.

		// 1) Check that the given publication type is supported by this filter
		// If no publication type is given then we'll support the description
		// by default.
		if (is_a($this->getInputType(), 'MetadataTypeDescription')) {
			$publicationType = $input->getStatement('[@publication-type]');
			if (!empty($publicationType) && !in_array($publicationType, $this->getSupportedPublicationTypes())) return false;
		}

		// 2) Check that the output actually contains data and is not an empty
		// description.
		if (!is_null($output) && is_a($output, 'MetadataDescription')) {
			$statements =& $output->getStatements();
			if (empty($statements)) return false;
		}

		return true;
	}

	//
	// Protected helper methods
	//
	/**
	 * Construct an array of search strings from a citation
	 * description and an array of search templates.
	 * The templates may contain the placeholders
	 *  %aulast%: the first author's surname
	 *  %au%:     the first author full name
	 *  %title%:  the article-title (if it exists),
	 *            otherwise the source
	 *  %date%:   the publication year
	 *  %isbn%:   ISBN
	 * @param $searchTemplates an array of templates
	 * @param $citationDescription MetadataDescription
	 * @return array
	 */
	function constructSearchStrings(&$searchTemplates, &$citationDescription) {
		// Convert first authors' name description to a string
		import('lib.pkp.classes.metadata.nlm.NlmNameSchemaPersonStringFilter');
		$personStringFilter = new NlmNameSchemaPersonStringFilter();

		// Retrieve the authors
		$firstAuthorSurname = $firstAuthor = '';
		$authors = $citationDescription->getStatement('person-group[@person-group-type="author"]');
		if (is_array($authors) && count($authors)) {
			$firstAuthorSurname = (string)$authors[0]->getStatement('surname');
			$firstAuthor = $personStringFilter->execute($authors[0]);
		}

		// Retrieve the editors
		$firstEditorSurname = $firstEditor = '';
		$editors = $citationDescription->getStatement('person-group[@person-group-type="editor"]');
		if (is_array($editors) && count($editors)) {
			$firstEditorSurname = (string)$editors[0]->getStatement('surname');
			$firstEditor = $personStringFilter->execute($editors[0]);
		}

		// Retrieve (default language) title
		$title = (string)($citationDescription->hasStatement('article-title') ?
				$citationDescription->getStatement('article-title') :
				$citationDescription->getStatement('source'));

		// Extract the year from the publication date
		$year = (string)$citationDescription->getStatement('date');
		$year = (String::strlen($year) > 4 ? String::substr($year, 0, 4) : $year);

		// Retrieve ISBN
		$isbn = (string)$citationDescription->getStatement('isbn');

		// Replace the placeholders in the templates
		$searchStrings = array();
		foreach($searchTemplates as $searchTemplate) {
			// Try editors and authors separately
			$searchStrings[] = str_replace(
					array('%aulast%', '%au%', '%title%', '%date%', '%isbn%'),
					array($firstAuthorSurname, $firstAuthor, $title, $year, $isbn),
					$searchTemplate
				);
			$searchStrings[] = str_replace(
					array('%aulast%', '%au%', '%title%', '%date%', '%isbn%'),
					array($firstEditorSurname, $firstEditor, $title, $year, $isbn),
					$searchTemplate
				);
		}

		// Remove empty or duplicate searches
		$searchStrings = array_map(array('String', 'trimPunctuation'), $searchStrings);
		$searchStrings = array_unique($searchStrings);
		$searchStrings = arrayClean($searchStrings);

		return $searchStrings;
	}

	/**
	 * Call web service with the given parameters
	 * @param $params array GET or POST parameters
	 * @return DOMDocument or null in case of error
	 */
	function &callWebService($url, &$params, $returnType = XSL_TRANSFORMER_DOCTYPE_DOM, $method = 'GET') {
		// Create a request
		$webServiceRequest = new WebServiceRequest($url, $params, $method);

		// Configure and call the web service
		$xmlWebService = new XmlWebService();
		$xmlWebService->setReturnType($returnType);
		$result =& $xmlWebService->call($webServiceRequest);

		if (is_null($result)) {
			// Construct a helpful error message including
			// the offending webservice url for get requests.
			$webserviceUrl = $url;
			if ($method == 'GET') {
				$keyValuePairs = array();
				foreach ($params as $key => $value) {
					$keyValuePairs[] = $key.'='.$value;
				}
				$webserviceUrl .= '?'.implode('&', $keyValuePairs);
			}

			$translationParams = array(
				'filterName' => $this->getDisplayName(),
				'webserviceUrl' => $webserviceUrl,
				'httpMethod' => $method
			);
			$this->addError(Locale::translate('submission.citations.filter.webserviceError', $translationParams));
		}

		return $result;
	}

	/**
	 * Takes the raw xml result of a web service and
	 * transforms it via XSL to a (preliminary) XML similar
	 * to NLM which is then re-encoded into an array. Finally
	 * some typical post-processing is performed.
	 * FIXME: Rewrite parser/lookup filter XSL to produce real NLM
	 * element-citation XML and factor this code into an NLM XML to
	 * NLM description filter.
	 * @param $xmlResult string or DOMDocument
	 * @param $xslFileName string
	 * @return array a metadata array
	 */
	function &transformWebServiceResults(&$xmlResult, $xslFileName) {
		// Send the result through the XSL to generate a (preliminary) NLM XML.
		$xslFilter = new XSLTransformationFilter('Web Service Transformation', array('xml::*', 'xml::*'));
		$xslFilter->setXSLFilename($xslFileName);
		$xslFilter->setResultType(XSL_TRANSFORMER_DOCTYPE_DOM);
		$preliminaryNlmDOM =& $xslFilter->execute($xmlResult);
		if (is_null($preliminaryNlmDOM) || is_null($preliminaryNlmDOM->documentElement)) {
			$translationParams = array('filterName' => $this->getDisplayName());
			$this->addError(Locale::translate('submission.citations.filter.webserviceResultTransformationError', $translationParams));
			$nullVar = null;
			return $nullVar;
		}

		// Transform the result to an array.
		$xmlHelper = new XMLHelper();
		$preliminaryNlmArray = $xmlHelper->xmlToArray($preliminaryNlmDOM->documentElement);

		$preliminaryNlmArray =& $this->postProcessMetadataArray($preliminaryNlmArray);

		return $preliminaryNlmArray;
	}

	/**
	 * Post processes an NLM meta-data array
	 * @param $preliminaryNlmArray array
	 * @return array
	 */
	function &postProcessMetadataArray(&$preliminaryNlmArray) {
		// Clean array
		$preliminaryNlmArray =& arrayClean($preliminaryNlmArray);

		// Trim punctuation
		$preliminaryNlmArray =& $this->_recursivelyTrimPunctuation($preliminaryNlmArray);

		// Parse (=filter) author/editor strings into NLM name descriptions
		foreach(array('author' => ASSOC_TYPE_AUTHOR, 'editor' => ASSOC_TYPE_EDITOR) as $personType => $personAssocType) {
			if (isset($preliminaryNlmArray[$personType])) {
				// Get the author/editor strings from the result
				$personStrings = $preliminaryNlmArray[$personType];
				unset($preliminaryNlmArray[$personType]);

				// Parse the author/editor strings into NLM name descriptions
				$personStringFilter = new PersonStringNlmNameSchemaFilter($personAssocType);
				// Interpret a scalar as a textual authors list
				if (is_scalar($personStrings)) {
					$personStringFilter->setFilterMode(PERSON_STRING_FILTER_MULTIPLE);
					$persons =& $personStringFilter->execute($personStrings);
				} else {
					$persons =& array_map(array($personStringFilter, 'execute'), $personStrings);
				}

				$preliminaryNlmArray['person-group[@person-group-type="'.$personType.'"]'] = $persons;
				unset($persons);
			}
		}

		// Join comments
		if (isset($preliminaryNlmArray['comment']) && is_array($preliminaryNlmArray['comment'])) {
			// Implode comments from the result into a single string
			// as required by the NLM citation schema.
			$preliminaryNlmArray['comment'] = implode("\n", $preliminaryNlmArray['comment']);
		}

		// Normalize date strings
		foreach(array('date', 'conf-date', 'access-date') as $dateProperty) {
			if (isset($preliminaryNlmArray[$dateProperty])) {
				$dateFilter = new DateStringNormalizerFilter();
				$preliminaryNlmArray[$dateProperty] = $dateFilter->execute($preliminaryNlmArray[$dateProperty]);
			}
		}

		// Cast strings to integers where necessary
		foreach(array('fpage', 'lpage', 'size') as $integerProperty) {
			if (isset($preliminaryNlmArray[$integerProperty]) && is_numeric($preliminaryNlmArray[$integerProperty])) {
				$preliminaryNlmArray[$integerProperty] = (integer)$preliminaryNlmArray[$integerProperty];
			}
		}

		// Rename elements that are stored in attributes in NLM citation
		$elementToAttributeMap = array(
			'access-date' => 'date-in-citation[@content-type="access-date"]',
			'issn-ppub' => 'issn[@pub-type="ppub"]',
			'issn-epub' => 'issn[@pub-type="epub"]',
			'pub-id-doi' => 'pub-id[@pub-id-type="doi"]',
			'pub-id-publisher-id' => 'pub-id[@pub-id-type="publisher-id"]',
			'pub-id-coden' => 'pub-id[@pub-id-type="coden"]',
			'pub-id-sici' => 'pub-id[@pub-id-type="sici"]',
			'pub-id-pmid' => 'pub-id[@pub-id-type="pmid"]',
			'publication-type' => '[@publication-type]'
		);
		foreach($elementToAttributeMap as $elementName => $nlmPropertyName) {
			if (isset($preliminaryNlmArray[$elementName])) {
				$preliminaryNlmArray[$nlmPropertyName] = $preliminaryNlmArray[$elementName];
				unset($preliminaryNlmArray[$elementName]);
			}
		}

		return $preliminaryNlmArray;
	}

	/**
	 * Creates a new NLM citation description and adds the data
	 * of an array of property/value pairs as statements.
	 * @param $metadataArray array
	 * @return MetadataDescription
	 */
	function &getNlmCitationDescriptionFromMetadataArray(&$metadataArray) {
		// Create a new citation description
		$citationDescription = new MetadataDescription('lib.pkp.classes.metadata.nlm.NlmCitationSchema', ASSOC_TYPE_CITATION);

		// Add the meta-data to the description
		$metadataArray = arrayClean($metadataArray);
		if (!$citationDescription->setStatements($metadataArray)) {
			$translationParams = array('filterName' => $this->getDisplayName());
			$this->addError(Locale::translate('submission.citations.filter.invalidMetadata', $translationParams));
			$nullVar = null;
			return $nullVar;
		}

		// Set display name and sequence id in the meta-data description
		// to the corresponding values from the filter. This is important
		// so that we later know which result came from which filter.
		$citationDescription->setDisplayName($this->getDisplayName());
		$citationDescription->setSeq($this->getSeq());

		return $citationDescription;
	}

	/**
	 * Take an NLM preliminary meta-data array and fix publisher-loc
	 * and publisher-name entries:
	 * - If there is a location but no name then try to extract a
	 *   publisher name from the location string.
	 * - Make sure that location and name are not the same.
	 * - Copy institution to publisher if no publisher is set,
	 *   otherwise leave the institution.
	 * @param $metadata array
	 * @return array
	 */
	function &fixPublisherNameAndLocation(&$metadata) {
		if (isset($metadata['publisher-loc'])) {
			// Extract publisher-name from publisher-loc if we don't have a
			// publisher-name in the parsing result.
			if (empty($metadata['publisher-name'])) {
				$metadata['publisher-name'] = String::regexp_replace('/.*:([^,]+),?.*/', '\1', $metadata['publisher-loc']);
			}

			// Remove publisher-name from publisher-loc
			$metadata['publisher-loc'] = String::regexp_replace('/^(.+):.*/', '\1', $metadata['publisher-loc']);

			// Check that publisher-name and location are not the same
			if (!empty($metadata['publisher-name']) && $metadata['publisher-name'] == $metadata['publisher-loc']) unset($metadata['publisher-name']);
		}

		// Copy the institution property (if any) as the publisher-name
		if (isset($metadata['institution']) &&
				(!isset($metadata['publisher-name']) || empty($metadata['publisher-name']))) {
			$metadata['publisher-name'] = $metadata['institution'];
		}

		// Clean the result
		foreach(array('publisher-name', 'publisher-loc') as $publisherProperty) {
			if (isset($metadata[$publisherProperty])) {
				$metadata[$publisherProperty] = String::trimPunctuation($metadata[$publisherProperty]);
			}
		}

		return $metadata;
	}

	//
	// Private helper methods
	//
	/**
	 * Recursively trim punctuation from a metadata array.
	 */
	function &_recursivelyTrimPunctuation(&$metadataArray) {
		assert(is_array($metadataArray));
		foreach($metadataArray as $metadataKey => $metadataValue) {
			// If we find an array then we'll recurse
			if (is_array($metadataValue)) {
				$metadataArray[$metadataKey] = $this->_recursivelyTrimPunctuation($metadataValue);
			}

			// String scalars will be trimmed
			if (is_string($metadataValue)) {
				$metadataArray[$metadataKey] = String::trimPunctuation($metadataValue);
			}

			// All other value types (i.e. integers, composite values, etc.)
			// will be ignored.
		}
		return $metadataArray;
	}

	/**
	 * Static method that returns a list of permitted
	 * publication types.
	 * NB: PHP4 workaround for static class member.
	 */
	function _allowedPublicationTypes() {
		static $allowedPublicationTypes = array(
			NLM_PUBLICATION_TYPE_JOURNAL,
			NLM_PUBLICATION_TYPE_CONFPROC,
			NLM_PUBLICATION_TYPE_BOOK,
			NLM_PUBLICATION_TYPE_THESIS
		);
		return $allowedPublicationTypes;
	}
}
?>