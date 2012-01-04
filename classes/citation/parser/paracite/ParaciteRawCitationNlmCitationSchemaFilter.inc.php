<?php

/**
 * @defgroup citation_parser_paracite
 */

/**
 * @file classes/citation/parser/paracite/ParaciteRawCitationNlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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
 *  and Text::Unidecode packages to be installed on the server. It also
 *  requires the PHP shell_exec() function to be available which is often
 *  disabled in shared hosting environments.
 */

// $Id$

import('citation.NlmCitationSchemaFilter');
import('metadata.nlm.OpenUrlNlmCitationSchemaCrosswalkFilter');
import('metadata.openurl.OpenUrlBookSchema');
import('metadata.openurl.OpenUrlJournalSchema');

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
	 * @param $output mixed
	 * @return boolean
	 */
	function supports(&$input, &$output) {
		return parent::supports($input, $output, true);
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

		// We have to merge subtitle and title as neither OpenURL
		// nor NLM can handle subtitles.
		if (isset($metadata['subtitle'])) {
			$metadata['title'] .= '. '.$metadata['subtitle'];
			unset($metadata['subtitle']);
		}

		// Break up the authors field
		if (isset($metadata['authors'])) {
			$metadata['authors'] = String::trimPunctuation($metadata['authors']);
			$metadata['authors'] = String::iterativeExplode(array(':', ';'), $metadata['authors']);
		}

		// Convert pages to integers
		foreach(array('spage', 'epage') as $pageProperty) {
			if (isset($metadata[$pageProperty])) $metadata[$pageProperty] = (integer)$metadata[$pageProperty];
		}

		// Convert titles to title case
		foreach(array('title', 'chapter', 'publication') as $titleProperty) {
			if (isset($metadata[$titleProperty])) $metadata[$titleProperty] = String::titleCase($metadata[$titleProperty]);
		}

		// Map ParaCite results to OpenURL - null means
		// throw the value away.
		$metadataMapping = array(
			'genre' => 'genre',
			'_class' => null,
			'any' => null,
			'authors' => 'au',
			'aufirst' => 'aufirst',
			'aufull' => null,
			'auinit' => 'auinit',
			'aulast' => 'aulast',
			'atitle' => 'atitle',
			'cappublication' => null,
			'captitle' => null,
			'date' => 'date',
			'epage' => 'epage',
			'featureID' => null,
			'id' => null,
			'issue' => 'issue',
			'jnl_epos' => null,
			'jnl_spos' => null,
			'match' => null,
			'marked' => null,
			'num_of_fig' => null,
			'pages' => 'pages',
			'publisher' => 'pub',
			'publoc' => 'place',
			'ref' => null,
			'rest_text' => null,
			'spage' => 'spage',
			'targetURL' => 'url',
			'text' => null,
			'ucpublication' => null,
			'uctitle' => null,
			'volume' => 'volume',
			'year' => 'date'
		);

		// Ignore 'year' if 'date' is set
		if (isset($metadata['date'])) {
			$metadataMapping['year'] = null;
		}

		// Set default genre
		if (empty($metadata['genre'])) $metadata['genre'] = 'article';

		// Handle title, chapter and publication depending on
		// the (inferred) genre. Also instantiate the target schema.
		switch($metadata['genre']) {
			case OPENURL_GENRE_BOOK:
			case OPENURL_GENRE_BOOKITEM:
			case OPENURL_GENRE_REPORT:
			case OPENURL_GENRE_DOCUMENT:
				$metadataMapping += array(
					'publication' => 'btitle',
					'chapter' => 'atitle'
				);
				if (isset($metadata['title'])) {
					if (!isset($metadata['publication'])) {
						$metadata['publication'] = $metadata['title'];
					} elseif (!isset($metadata['chapter'])) {
						$metadata['chapter'] = $metadata['title'];
					}
					unset($metadata['title']);
				}
				$openUrlSchema = new OpenUrlBookSchema();
				break;

			case OPENURL_GENRE_ARTICLE:
			case OPENURL_GENRE_JOURNAL:
			case OPENURL_GENRE_ISSUE:
			case OPENURL_GENRE_CONFERENCE:
			case OPENURL_GENRE_PROCEEDING:
			case OPENURL_GENRE_PREPRINT:
			default:
				$metadataMapping += array('publication' => 'jtitle');
				if (isset($metadata['title'])) {
					if (!isset($metadata['publication'])) {
						$metadata['publication'] = $metadata['title'];
					} elseif (!isset($metadata['atitle'])) {
						$metadata['atitle'] = $metadata['title'];
					}
					unset($metadata['title']);
				}
				$openUrlSchema = new OpenUrlJournalSchema();
				break;
		}

		// Instantiate an OpenURL description
		$openUrlDescription = new MetadataDescription($openUrlSchema, ASSOC_TYPE_CITATION);

		// Map the ParaCite result to OpenURL
		foreach ($metadata as $paraciteElementName => $paraciteValue) {
			if (!empty($paraciteValue)) {
				// Trim punctuation
				if (is_string($paraciteValue)) $paraciteValue = String::trimPunctuation($paraciteValue);

				// Transfer the value to the OpenURL result array
				assert(array_key_exists($paraciteElementName, $metadataMapping));
				$openUrlPropertyName = $metadataMapping[$paraciteElementName];
				if (!is_null($openUrlPropertyName) && $openUrlSchema->hasProperty($openUrlPropertyName)) {
					if (is_array($paraciteValue)) {
						foreach($paraciteValue as $singleValue) {
							$success = $openUrlDescription->addStatement($openUrlPropertyName, $singleValue);
							assert($success);
						}
					} else {
						$success = $openUrlDescription->addStatement($openUrlPropertyName, $paraciteValue);
						assert($success);
					}
				}
			}
		}

		// Crosswalk to NLM
		$crosswalkFilter = new OpenUrlNlmCitationSchemaCrosswalkFilter();
		$nlmDescription =& $crosswalkFilter->execute($openUrlDescription);
		assert(is_a($nlmDescription, 'MetadataDescription'));

		// Add 'rest_text' as NLM comment (if given)
		if (isset($metadata['rest_text'])) {
			$nlmDescription->addStatement('comment', String::trimPunctuation($metadata['rest_text']));
		}

		return $nlmDescription;
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