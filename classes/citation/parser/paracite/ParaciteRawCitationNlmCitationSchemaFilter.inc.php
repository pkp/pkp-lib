<?php

/**
 * @file classes/citation/parser/paracite/ParaciteRawCitationNlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParaciteRawCitationNlmCitationSchemaFilter
 * @ingroup citation_parser_paracite
 *
 * @brief Paracite parsing filter implementation.
 *
 *  The paracite parsing filter has one parameter: the citation module
 *  to be used. This can be one of "Standard", "Citebase" or "Jiao".
 *
 *  If you want to use various modules at the same time then you have
 *  to instantiate this parser filter several times with different
 *  configuration and chain all instances.
 *
 *  NB: This filter requires perl and CPAN's Biblio::Citation::Parser
 *  package to be installed on the server. It also requires the PHP
 *  shell_exec() function to be available which is often disabled in
 *  shared hosting environments.
 */

// $Id$

import('citation.NlmCitationSchemaFilter');

define('CITATION_PARSER_PARACITE_STANDARD', 'Standard');
define('CITATION_PARSER_PARACITE_CITEBASE', 'Citebase');
define('CITATION_PARSER_PARACITE_JIAO', 'Jiao');

class ParaciteRawCitationNlmCitationSchemaFilter extends NlmCitationSchemaFilter {
	/** @var string the paracite citation parser module to be used (default: 'Standard') */
	var $_citationModule;

	/*
	 * Constructor
	 */
	function ParaciteRawCitationNlmCitationSchemaFilter($citationModule  = CITATION_PARSER_PARACITE_STANDARD) {
		assert(in_array($citationModule, ParaciteRawCitationNlmCitationSchemaFilter::getSupportedCitationModules()));
		$this->_citationModule = $citationModule;
		parent::NlmCitationSchemaFilter();
	}

	//
	// Getters and Setters
	//
	/**
	 * get the citationModule
	 * @return string
	 */
	function getCitationModule() {
		return $this->_citationModule;
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
		return is_string($input);
	}

	/**
	 * @see Filter::process()
	 * @param $citationString string
	 * @return MetadataDescription
	 */
	function &process($citationString) {
		$nullVar = null;

		// Check the availability of perl
		$perlCommand = Config::getVar('cli', 'perl');
		if (empty($perlCommand) || !file_exists($perlCommand)) return $nullVar;

		// Convert to ASCII - Paracite doesn't handle UTF-8 well
		$citationString = String::utf8_to_ascii($citationString);

		// Call the paracite parser
		$wrapperScript = dirname(__FILE__).DIRECTORY_SEPARATOR.'paracite.pl';
		$paraciteCommand = $perlCommand.' '.escapeshellarg($wrapperScript).' '.
		                   $this->_citationModule.' '.escapeshellarg($citationString);
		$xmlResult = shell_exec($paraciteCommand);
		if (empty($xmlResult)) return $nullVar;

		if ( Config::getVar('i18n', 'charset_normalization') == 'On' && !String::utf8_compliant($xmlResult) ) {
			$xmlResult = String::utf8_normalize($xmlResult);
		}

		// Create a temporary DOM document
		$resultDOM = new DOMDocument();
		$resultDOM->recover = true;
		$resultDOM->loadXML($xmlResult);

		// Extract the parser results as an array
		$xmlHelper = new XMLHelper();
		$metadata = $xmlHelper->xmlToArray($resultDOM->documentElement);

		// Translate genre to publication type
		if (isset($metadata['genre'])) {
			$genreTranslationMapping = CitationService::_getGenreTranslationMapping();
			assert(isset($genreTranslationMapping[$metadata['genre']]));
			$metadata['publication-type'] = $genreTranslationMapping[$metadata['genre']];
			unset($metadata['genre']);
		}

		// Correct title capitalization
		if (isset($metadata['title'])) {
			$metadata['title'] = $this->titleCase($metadata['title']);
		}

		// Set meta-data elements that need special handling
		if (isset($metadata['publication'])) {
			if (isset($metadata['genre'])) {
				switch($metadata['genre']) {
					case METADATA_GENRE_BOOK:
						$citation->setBookTitle($metadata['publication']);
						break;

					default:
						// Should not be reachable
						assert(false);
				}
			} else {
				$citation->setJournalTitle($metadata['publication']);
			}
		}
		if (isset($metadata['date'])) {
			$citation->setIssuedDate($this->normalizeDateString($metadata['date']));
		} elseif (isset($metadata['year'])) {
			$citation->setIssuedDate($this->normalizeDateString($metadata['year']));
		}
		if (isset($metadata['authors'])) $citation->setAuthors($this->parseAuthorsString($metadata['authors']));
		unset($metadata['publication'], $metadata['date'], $metadata['year'], $metadata['authors']);

		// Map elements from paracite to our internal format
		$metadataMapping = array(
			'_class' => null,
			'any' => null,
			'aufirst' => null,
			'aufull' => null,
			'auinit' => null,
			'aulast' => null,
			'atitle' => 'articleTitle',
			'cappublication' => null,
			'captitle' => null,
			'chapter' => null,
			'epage' => 'lastPage',
			'featureID' => null,
			'id' => null,
			'jnl_epos' => null,
			'jnl_spos' => null,
			'match' => null,
			'marked' => null,
			'num_of_fig' => null,
			'pages' => null,
			'publoc' => 'place',
			'ref' => null,
			'rest_text' => 'comment',
			'spage' => 'firstPage',
			'subtitle' => null,
			'targetURL' => 'url',
			'text' => null,
			'title' => 'articleTitle',
			'ucpublication' => null,
			'uctitle' => null,
		);
		foreach ($metadataMapping as $paraciteElementName => $elementName) {
			if (isset($metadata[$paraciteElementName]) && !empty($metadata[$paraciteElementName])) {
				if (!is_null($elementName)) {
					if ($elementName == 'comment') {
						if (!isset($metadata['comments'])) $metadata['comments'] = array();
						$metadata['comments'][] = $metadata[$paraciteElementName];
					} else {
						$metadata[$elementName] = $metadata[$paraciteElementName];
					}
				}
				unset($metadata[$paraciteElementName]);
			}
		}

		if (!$citation->setElementsFromArray($metadata)) {
			// Catch invalid metadata error condition
			$citation = null;
			return;
		}
	}


	//
	// Private helper methods
	//
	/**
	 * Return supported paracite citation parser modules
	 * NB: PHP4 work-around for a public static class member
	 * @return array supported citation modules
	 */
	function getSupportedCitationModules() {
		static $_supportedCitationModules = array(
			CITATION_PARSER_PARACITE_STANDARD,
			CITATION_PARSER_PARACITE_CITEBASE,
			CITATION_PARSER_PARACITE_JIAO
		);

		return $_supportedCitationModules;
	}
}
?>