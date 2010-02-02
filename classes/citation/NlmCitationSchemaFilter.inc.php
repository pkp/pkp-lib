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
	 * FIXME: Rewrite parser/lookup filter XSL to produce real NLM
	 * element-citation XML and factor this code into an NLM XML to
	 * NLM description filter.
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

		// Parse (=filter) author/editor strings into NLM name descriptions
		foreach(array('author', 'editor') as $personType) {
			if (isset($preliminaryNlmArray[$personType])) {
				// Get the author strings from the result
				$personStrings = $preliminaryNlmArray[$personType];
				unset($preliminaryNlmArray[$personType]);

				// If we only have one author then we'll have to
				// convert the author strings to an array first.
				if (!is_array($personStrings)) $personStrings = array($personStrings);

				$personStringFilter = new PersonStringNlmNameSchemaFilter(ASSOC_TYPE_AUTHOR);
				$persons = array();
				foreach ($personStrings as $personString) {
					$persons[] =& $personStringFilter->execute($personString);
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
		foreach(array('date', 'conf-date') as $dateProperty) {
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