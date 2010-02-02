<?php

/**
 * @file classes/citation/NlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaFilter
 * @ingroup metadata_nlm
 *
 * @brief Abstract base class for all filters that transform
 *  NLM citation metadata descriptions.
 */

// $Id$

import('filter.Filter');

import('metadata.MetadataDescription');
import('metadata.nlm.NlmCitationSchema');
import('metadata.nlm.NlmNameSchema');
import('metadata.nlm.PersonStringNlmNameSchemaFilter');
import('metadata.DateStringNormalizerFilter');

import('webservice.XmlWebService');

import('xml.XMLHelper');
import('xslt.XSLTransformationFilter');

class NlmCitationSchemaFilter extends Filter {
	/** @var array */
	var $_supportedPublicationTypes;

	/**
	 * Constructor
	 */
	function NlmCitationSchemaFilter($supportedPublicationTypes) {
		assert(is_array($supportedPublicationTypes));
		foreach ($supportedPublicationTypes as $supportedPublicationType) {
			assert(in_array($supportedPublicationType, $this->_allowedPublicationTypes()));
		}
		$this->_supportedPublicationTypes = $supportedPublicationTypes;
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

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @return boolean
	 */
	function supports(&$input) {
		// This filter requires PHP5's DOMDocument
		if (!checkPhpVersion('5.0.0')) return false;

		if (!$this->isNlmCitationDescription($input)) return false;

		// Check that the given publication type is supported by this filter
		$publicationType = $input->getStatement('[@publication-type]');
		if (!empty($publicationType) && !in_array($publicationType, $this->getSupportedPublicationTypes())) return false;

		return true;
	}

	/**
	 * @see Filter::isValid()
	 * @param $output mixed
	 * @return boolean
	 */
	function isValid(&$output) {
		return $this->isNlmCitationDescription($output);
	}

	//
	// Protected helper methods
	//
	/**
	 * Checks whether a given input is a nlm citation description
	 * @param $metadataDescription mixed
	 * @return boolean
	 */
	function isNlmCitationDescription(&$metadataDescription) {
		if (!is_a($metadataDescription, 'MetadataDescription')) return false;
		$metadataSchema =& $metadataDescription->getMetadataSchema();
		if ($metadataSchema->getName() != 'nlm-3.0-element-citation') return false;
		return true;
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

		return $result;
	}

	/**
	 * Takes the raw xml result of a web service and
	 * transforms it via XSL to a (preliminary) XML similar
	 * to NLM which is then re-encoded into an array. Finally
	 * some typical post-processing is performed.
	 * @param $xmlResult string or DOMDocument
	 * @param $xslFileName string
	 * @return array a metadata array
	 */
	function &transformWebServiceResults(&$xmlResult, $xslFileName) {
		// Send the result through the XSL to generate a (preliminary) NLM XML.
		$xslFilter = new XSLTransformationFilter();
		$xslFilter->setXSLFilename($xslFileName);
		$xslFilter->setResultType(XSL_TRANSFORMER_DOCTYPE_DOM);
		$preliminaryNlmDOM =& $xslFilter->execute($xmlResult);
		if (is_null($preliminaryNlmDOM)) return $preliminaryNlmDOM;

		// Transform the result to an array.
		$xmlHelper = new XMLHelper();
		$preliminaryNlmArray = $xmlHelper->xmlToArray($preliminaryNlmDOM->documentElement);

		// Parse (=filter) author strings into NLM name descriptions
		if (isset($preliminaryNlmArray['author'])) {
			// Get the author strings from the result
			$authorStrings = $preliminaryNlmArray['author'];
			unset($preliminaryNlmArray['author']);

			// If we only have one author then we'll have to
			// convert the author strings to an array first.
			if (!is_array($authorStrings)) $authorStrings = array($authorStrings);

			$personStringFilter = new PersonStringNlmNameSchemaFilter(ASSOC_TYPE_AUTHOR);
			$authors = array();
			foreach ($authorStrings as $authorString) {
				$authors[] =& $personStringFilter->execute($authorString);
			}
			$preliminaryNlmArray['person-group[@person-group-type="author"]'] = $authors;
		}

		// Transform comments
		if (isset($preliminaryNlmArray['comment'])) {
			// Get comments from the result
			$comments = $preliminaryNlmArray['comment'];
			unset($preliminaryNlmArray['comment']);

			// If we only have one comment then we'll have to
			// convert the it to an array.
			if (!is_array($comments)) $comments = array($comments);

			$preliminaryNlmArray['comments'] = $comments;
		}

		// Parse date string
		if (isset($preliminaryNlmArray['date'])) {
			$dateFilter = new DateStringNormalizerFilter();
			$preliminaryNlmArray['date'] = $dateFilter->execute($preliminaryNlmArray['date']);
		}

		return $preliminaryNlmArray;
	}

	/**
	 * Sets the data of an array of property/value pairs
	 * as statements in an NLM citation description.
	 * @param $metadataArray array
	 * @return MetadataDescription
	 */
	function &createNlmCitationDescriptionFromArray(&$metadataArray) {
		// Trim punctuation
		$metadataArray =& $this->_recursivelyTrimPunctuation($metadataArray);

		// Create the citation description
		$metadataSchema = new NlmCitationSchema();
		$citationDescription = new MetadataDescription($metadataSchema, ASSOC_TYPE_CITATION);

		// Add the meta-data to the description
		if (!$citationDescription->setStatements($metadataArray)) {
			$nullVar = null;
			return $nullVar;
		}

		return $citationDescription;
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
			'journal', 'conf-proc', 'book', 'thesis'
		);
		return $allowedPublicationTypes;
	}
}
?>