<?php

/**
 * @file classes/metadata/NlmCitationSchemaOpenUrlCrosswalkFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaOpenUrlCrosswalkFilter
 * @ingroup metadata_nlm
 * @see NlmCitationSchema
 * @see OpenUrlBookSchema
 * @see OpenUrlJournalSchema
 * @see OpenUrlDissertationSchema
 *
 * @brief Filter that converts from NLM citation to
 *  OpenURL schemas.
 */

// $Id$

import('metadata.CrosswalkFilter');
import('metadata.nlm.NlmCitationSchema');
import('metadata.openurl.OpenUrlBaseSchema');

class NlmCitationSchemaOpenUrlCrosswalkFilter extends CrosswalkFilter {
	/**
	 * Constructor
	 */
	function NlmCitationSchemaOpenUrlCrosswalkFilter() {
		// We transform NLM citation to all types of OpenURL schema
		parent::CrosswalkFilter('NlmCitationSchema', 'OpenUrlBaseSchema');
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * Map NLM properties to OpenURL properties.
	 * NB: OpenURL has no i18n so we use the default
	 * locale when mapping.
	 * @see Filter::process()
	 * @param $input MetadataDescription
	 * @return MetadataDescription
	 */
	function &process(&$input) {
		$nullVar = null;
		// Identify the genre of the target record and
		// instantiate the target description.
		$publicationType = $input->getStatement('[@publication-type]');
		switch($publicationType) {
			case 'journal':
			case 'conf-proc':
				import('metadata.openurl.OpenUrlJournalSchema');
				$outputSchema = new OpenUrlJournalSchema();
				break;

			case 'book':
				import('metadata.openurl.OpenUrlBookSchema');
				$outputSchema = new OpenUrlBookSchema();
				break;

			case 'thesis':
				import('metadata.openurl.OpenUrlDissertationSchema');
				$outputSchema = new OpenUrlDissertationSchema();
				break;

			default:
				// Unsupported type
				return $nullVar;
		}

		// Create the target description
		$output = new MetadataDescription($outputSchema, $input->getAssocType());

		// Transform authors
		import('metadata.nlm.NlmNameSchemaPersonStringFilter');
		$personStringFilter = new NlmNameSchemaPersonStringFilter();
		$authors =& $input->getStatement('person-group[@person-group-type="author"]');
		if (is_array($authors) && count($authors)) {
			$aulast = ($authors[0]->hasStatement('prefix') ? $authors[0]->getStatement('prefix').' ' : '');
			$aulast .= $authors[0]->getStatement('surname');
			if (!empty($aulast)) {
				$success = $output->addStatement('aulast', $aulast);
				assert($success);
			}

			$givenNames = $authors[0]->getStatement('given-names');
			if(is_array($givenNames) && count($givenNames)) {
				$aufirst = implode(' ', $givenNames);
				if (!empty($aufirst)) {
					$success = $output->addStatement('aufirst', $aufirst);
					assert($success);
				}

				$initials = array();
				foreach($givenNames as $givenName) {
					$initials[] = substr($givenName, 0, 1);
				}

				$auinit1 = array_shift($initials);
				if (!empty($auinit1)) {
					$success = $output->addStatement('auinit1', $auinit1);
					assert($success);
				}

				$auinitm = implode('', $initials);
				if (!empty($auinitm)) {
					$success = $output->addStatement('auinitm', $auinitm);
					assert($success);
				}

				$auinit = $auinit1.$auinitm;
				if (!empty($auinit)) {
					$success = $output->addStatement('auinit', $auinit);
					assert($success);
				}
			}

			$ausuffix = $authors[0]->getStatement('suffix');
			if (!empty($ausuffix)) {
				$success = $output->addStatement('ausuffix', $ausuffix);
				assert($success);
			}

			foreach ($authors as $author) {
				$au = $personStringFilter->execute($author);
				$success = $output->addStatement('au', $au);
				assert($success);
				unset($au);
			}
		}

		// Map properties (NLM => OpenURL)
		$propertyMap = array();

		// Map titles and date
		switch($publicationType) {
			case 'journal':
				$propertyMap['source'] = 'jtitle';
				$propertyMap['article-title'] = 'atitle';
				break;

			case 'conf-proc':
				$propertyMap['conf-name'] = 'jtitle';
				$propertyMap['article-title'] = 'atitle';
				if ($input->hasStatement('conf-date')) {
					$propertyMap['conf-date'] = 'date';
				}
				break;

			case 'book':
				$propertyMap['source'] = 'btitle';
				$propertyMap['chapter-title'] = 'atitle';
				break;

			case 'thesis':
				$propertyMap['article-title'] = 'title';
				break;
		}

		// Map the date (if it's not already mapped).
		if (!isset($propertyMap['conf-date'])) {
			$propertyMap['date'] = 'date';
		}

		// ISBN is common to all OpenURL schemas and
		// can be mapped one-to-one.
		$propertyMap['isbn'] = 'isbn';

		// Properties common to OpenURL book and journal
		if (is_a($output->getMetadataSchema(), 'OpenUrlJournalBookBaseSchema')) {
			// Genre: Guesswork
			switch($publicationType) {
				case 'journal':
					$genre = ($input->hasProperty('article-title') ? 'article' : 'journal');
					break;

				case 'conf-proc':
					$genre = ($input->hasProperty('article-title') ? 'proceeding' : 'conference');
					break;

				case 'book':
					$genre = ($input->hasProperty('article-title') ? 'bookitem' : 'book');
					break;
			}
			assert(!empty($genre));
			$success = $output->addStatement('genre', $genre);
			assert($success);

			// Some properties can be mapped one-to-one
			$propertyMap += array(
				'issn[@pub-type="ppub"]' => 'issn',
				'fpage' => 'spage',
				'lpage' => 'epage'
			);

			// FIXME: Map 'aucorp' for OpenURL journal/book when we
			// have 'collab' statements in NLM citation.
		}

		// OpenURL journal properties
		// The properties 'chron' and 'quarter' remain unmatched.
		if (is_a($output->getMetadataSchema(), 'OpenUrlJournalSchema')) {
			$propertyMap += array(
				'season' => 'ssn',
				'volume' => 'volume',
				'supplement' => 'part',
				'issue' => 'issue',
				'issn[@pub-type="epub"]' => 'eissn',
				'pub-id[@pub-id-type="publisher-id"]' => 'artnum',
				'pub-id[@pub-id-type="coden"]' => 'coden',
				'pub-id[@pub-id-type="sici"]' => 'sici'
			);
		}

		// OpenURL book properties
		// The 'bici' property remains unmatched.
		if (is_a($output->getMetadataSchema(), 'OpenUrlBookSchema')) {
			$propertyMap += array(
				'publisher-loc' => 'place',
				'publisher-name' => 'pub',
				'edition' => 'edition',
				'size' => 'tpages',
				'series' => 'series'
			);
		}

		// OpenURL dissertation properties
		// The properties 'cc', 'advisor' and 'degree' remain unmatched
		// as NLM does not have good dissertation support.
		if (is_a($output->getMetadataSchema(), 'OpenUrlDisertationSchema')) {
			$propertyMap += array(
				'size' => 'tpages',
				'publisher-loc' => 'co',
				'institution' => 'inst'
			);
		}

		// Transfer mapped properties with default locale
		foreach ($propertyMap as $nlmProperty => $openUrlProperty) {
			if ($input->hasStatement($nlmProperty)) {
				$success = $output->addStatement($openUrlProperty, $input->getStatement($nlmProperty));
				assert($success);
			}
		}

		return $output;
	}
}
?>