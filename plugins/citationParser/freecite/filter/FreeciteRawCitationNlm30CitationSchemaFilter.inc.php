<?php

/**
 * @defgroup plugins_citationParser_freecite_filter
 */

/**
 * @file plugins/citationParser/freecite/filter/FreeciteRawCitationNlm30CitationSchemaFilter.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FreeciteRawCitationNlm30CitationSchemaFilter
 * @ingroup plugins_citationParser_freecite_filter
 *
 * @brief Parsing filter implementation that uses the Freecite web service.
 *
 */


import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaFilter');

define('FREECITE_WEBSERVICE', 'http://freecite.library.brown.edu/citations/create');

class FreeciteRawCitationNlm30CitationSchemaFilter extends Nlm30CitationSchemaFilter {
	/*
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function FreeciteRawCitationNlm30CitationSchemaFilter(&$filterGroup) {
		$this->setDisplayName('FreeCite');

		parent::Nlm30CitationSchemaFilter($filterGroup);
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @see PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.citationParser.freecite.filter.FreeciteRawCitationNlm30CitationSchemaFilter';
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $citationString string
	 * @return MetadataDescription
	 */
	function &process($citationString) {
		$nullVar = null;

		// Freecite requires a post request
		$postData = array('citation' => $citationString);
		if (is_null($resultDOM = $this->callWebService(FREECITE_WEBSERVICE, $postData, XSL_TRANSFORMER_DOCTYPE_DOM, 'POST'))) return $nullVar;

		// Transform the result into an array of meta-data
		if (is_null($metadata =& $this->transformWebServiceResults($resultDOM, dirname(__FILE__).DIRECTORY_SEPARATOR.'freecite.xsl'))) return $nullVar;

		// Extract a publisher from the place string if possible
		$metadata =& $this->fixPublisherNameAndLocation($metadata);

		// Convert the genre
		if (isset($metadata['genre'])) {
			$genre = $metadata['genre'];
			import('lib.pkp.plugins.metadata.nlm30.filter.Openurl10Nlm30CitationSchemaCrosswalkFilter');
			$genreMap = Openurl10Nlm30CitationSchemaCrosswalkFilter::_getOpenurl10GenreTranslationMapping();
			$metadata['[@publication-type]'] = (isset($genreMap[$genre]) ? $genreMap[$genre] : $genre);
			unset($metadata['genre']);
		}

		// Convert article title to source for dissertations
		if (isset($metadata['[@publication-type]']) && $metadata['[@publication-type]'] == NLM30_PUBLICATION_TYPE_THESIS && isset($metadata['article-title'])) {
			$metadata['source'] = $metadata['article-title'];
			unset($metadata['article-title']);
		}

		unset($metadata['raw_string']);

		return $this->getNlm30CitationDescriptionFromMetadataArray($metadata);
	}
}
?>
