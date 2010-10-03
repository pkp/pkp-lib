<?php

/**
 * @defgroup citation_parser_paracite
 */

/**
 * @file classes/citation/parser/paracite/ParaciteRawCitationNlm30CitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParaciteRawCitationNlm30CitationSchemaFilter
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

import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaFilter');
import('lib.pkp.plugins.metadata.nlm30.filter.OpenUrl10Nlm30CitationSchemaCrosswalkFilter');
import('lib.pkp.classes.filter.SetFilterSetting');

define('CITATION_PARSER_PARACITE_STANDARD', 'Standard');
define('CITATION_PARSER_PARACITE_CITEBASE', 'Citebase');
define('CITATION_PARSER_PARACITE_JIAO', 'Jiao');

class ParaciteRawCitationNlm30CitationSchemaFilter extends Nlm30CitationSchemaFilter {
	/*
	 * Constructor
	 */
	function ParaciteRawCitationNlm30CitationSchemaFilter($citationModule = CITATION_PARSER_PARACITE_STANDARD) {
		$this->setDisplayName('ParaCite');

		assert(in_array($citationModule, ParaciteRawCitationNlm30CitationSchemaFilter::getSupportedCitationModules()));
		$this->setData('citationModule', $citationModule);

		// Instantiate the settings of this filter
		$citationModuleSetting = new SetFilterSetting('citationModule',
				'metadata.filters.paracite.settings.citationModule.displayName',
				'metadata.filters.paracite.settings.citationModule.validationMessage',
				ParaciteRawCitationNlm30CitationSchemaFilter::getSupportedCitationModules());
		$this->addSetting($citationModuleSetting);

		parent::Nlm30CitationSchemaFilter(NLM_CITATION_FILTER_PARSE);
	}

	//
	// Getters and Setters
	//
	/**
	 * get the citation module
	 * @return string
	 */
	function getCitationModule() {
		return $this->getData('citationModule');
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.parser.paracite.ParaciteRawCitationNlm30CitationSchemaFilter';
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
			$this->getCitationModule().' '.escapeshellarg($citationString);
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
		if (empty($metadata['genre'])) $metadata['genre'] = OPENURL_GENRE_ARTICLE;

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
				$openUrl10SchemaName = 'lib.pkp.plugins.metadata.openurl10.schema.OpenUrl10BookSchema';
				$openUrl10SchemaClass = 'OpenUrl10BookSchema';
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
				$openUrl10SchemaName = 'lib.pkp.plugins.metadata.openurl10.schema.OpenUrl10JournalSchema';
				$openUrl10SchemaClass = 'OpenUrl10JournalSchema';
				break;
		}

		// Instantiate an OpenURL description
		$openUrl10Description = new MetadataDescription($openUrl10SchemaName, ASSOC_TYPE_CITATION);
		$openUrl10Schema = new $openUrl10SchemaClass();

		// Map the ParaCite result to OpenURL
		foreach ($metadata as $paraciteElementName => $paraciteValue) {
			if (!empty($paraciteValue)) {
				// Trim punctuation
				if (is_string($paraciteValue)) $paraciteValue = String::trimPunctuation($paraciteValue);

				// Transfer the value to the OpenURL result array
				assert(array_key_exists($paraciteElementName, $metadataMapping));
				$openUrl10PropertyName = $metadataMapping[$paraciteElementName];
				if (!is_null($openUrl10PropertyName) && $openUrl10Schema->hasProperty($openUrl10PropertyName)) {
					if (is_array($paraciteValue)) {
						foreach($paraciteValue as $singleValue) {
							$success = $openUrl10Description->addStatement($openUrl10PropertyName, $singleValue);
							assert($success);
						}
					} else {
						$success = $openUrl10Description->addStatement($openUrl10PropertyName, $paraciteValue);
						assert($success);
					}
				}
			}
		}

		// Crosswalk to NLM
		$crosswalkFilter = new OpenUrl10Nlm30CitationSchemaCrosswalkFilter();
		$nlm30Description =& $crosswalkFilter->execute($openUrl10Description);
		assert(is_a($nlm30Description, 'MetadataDescription'));

		// Add 'rest_text' as NLM comment (if given)
		if (isset($metadata['rest_text'])) {
			$nlm30Description->addStatement('comment', String::trimPunctuation($metadata['rest_text']));
		}

		// Set display name and sequence id in the meta-data description
		// to the corresponding values from the filter. This is important
		// so that we later know which result came from which filter.
		$nlm30Description->setDisplayName($this->getDisplayName());
		$nlm30Description->setSeq($this->getSeq());

		return $nlm30Description;
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
