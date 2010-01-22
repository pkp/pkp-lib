<?php

/**
 * @file classes/citation/ParaciteCitationParserService.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParaciteCitationParserService
 * @ingroup citation
 * @see CitationMangager
 *
 * @brief Paracite parsing service implementation.
 *
 *        The paracite parsing service has one parameter: the citation module
 *        to be used. This can be one of "Standard", "Citebase" or "Jiao".
 *        
 *        If you want to use various modules at the same time then you have
 *        to instantiate this parser service several times with different
 *        configuration and chain all instances in the CitationManager's configured
 *        parser services.
 *        
 *        NB: This parser requires perl and CPAN's Biblio::Citation::Parser
 *        package to be installed on the server. It also requires the PHP
 *        shell_exec() function to be available which is often disabled in
 *        shared hosting environments.
 */

// $Id$

define('CITATION_PARSER_PARACITE_STANDARD', 'Standard');
define('CITATION_PARSER_PARACITE_CITEBASE', 'Citebase');
define('CITATION_PARSER_PARACITE_JIAO', 'Jiao');

import('citation.CitationParserService');

class ParaciteCitationParserService extends CitationParserService {
	/** @var string the paracite citation parser module to be used (default: 'Standard') */
	var $_citationModule = CITATION_PARSER_PARACITE_STANDARD;
	
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
	
	/**
	 * @see CitationParserService::parseInternal()
	 * @param $citationString string
	 * @param $citation Citation
	 */
	function parseInternal($citationString, &$citation) {
		// Check the availability of perl
		$perlCommand = Config::getVar('cli', 'perl');
		if (empty($perlCommand) || !file_exists($perlCommand)) {
			$citation = null;
			return;
		}

		// Convert to ASCII - Paracite doesn't handle UTF-8 well
		require_once('lib/phputf8/utf8_to_ascii.php');
		$citationString = utf8_to_ascii($citationString);

		// Call the paracite parser
		$wrapperScript = dirname(__FILE__).DIRECTORY_SEPARATOR.'paracite.pl';
		$paraciteCommand = $perlCommand.' '.escapeshellarg($wrapperScript).' '.
		                   $this->_citationModule.' '.escapeshellarg($citationString);
		$xmlResult = shell_exec($paraciteCommand);
		if (empty($xmlResult)) {
			$citation = null;
			return;
		}
		
		if ( Config::getVar('i18n', 'charset_normalization') == 'On' && !String::utf8_compliant($xmlResult) ) {
			$xmlResult = String::utf8_normalize($xmlResult);
		}
		
		// Create a temporary DOM document
		// FIXME: PHP5 only
		$resultDOM = new DOMDocument();
		// Try to handle non-well-formed responses.
		$resultDOM->recover = true; 

		$resultDOM->loadXML($xmlResult);
		$metadata = $this->xmlToArray($resultDOM->documentElement);

		// Translate the genre
		if (isset($metadata['genre'])) {
			$genreTranslationMapping = CitationService::_getGenreTranslationMapping();
			assert(isset($genreTranslationMapping[$metadata['genre']]));
			$metadata['genre'] = $genreTranslationMapping[$metadata['genre']];
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
	// Get/set methods
	//
	
	/**
	 * get the citationModule
	 * @return string
	 */
	function getCitationModule() {
		return $this->_citationModule;
	}
	
	/**
	 * set the citationModule
	 * @param $citationModule string
	 */
	function setCitationModule($citationModule) {
		assert(in_array($citationModule, ParaciteCitationParserService::getSupportedCitationModules()));
		$this->_citationModule = $citationModule;
	}
}
?>