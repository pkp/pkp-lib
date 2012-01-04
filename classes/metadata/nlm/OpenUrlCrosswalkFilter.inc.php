<?php

/**
 * @file classes/metadata/OpenUrlCrosswalkFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlCrosswalkFilter
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
import('metadata.openurl.OpenUrlJournalSchema');
import('metadata.openurl.OpenUrlBookSchema');
import('metadata.openurl.OpenUrlDissertationSchema');

class OpenUrlCrosswalkFilter extends CrosswalkFilter {
	/**
	 * Constructor
	 */
	function OpenUrlCrosswalkFilter($fromSchema, $toSchema) {
		parent::CrosswalkFilter($fromSchema, $toSchema);
	}

	//
	// Protected helper methods
	//
	/**
	 * Create a mapping of NLM properties to OpenURL
	 * properties that do not need special processing.
	 * @param $publicationType The NLM publication type
	 * @param $openUrlSchema MetadataSchema
	 * @return array
	 */
	function &nlmOpenUrlMapping($publicationType, &$openUrlSchema) {
		$propertyMap = array();

		// Map titles and date
		switch($publicationType) {
			case NLM_PUBLICATION_TYPE_JOURNAL:
				$propertyMap['source'] = 'jtitle';
				$propertyMap['article-title'] = 'atitle';
				break;

			case NLM_PUBLICATION_TYPE_CONFPROC:
				$propertyMap['conf-name'] = 'jtitle';
				$propertyMap['article-title'] = 'atitle';
				if ($input->hasStatement('conf-date')) {
					$propertyMap['conf-date'] = 'date';
				}
				break;

			case NLM_PUBLICATION_TYPE_BOOK:
				$propertyMap['source'] = 'btitle';
				$propertyMap['chapter-title'] = 'atitle';
				break;

			case NLM_PUBLICATION_TYPE_THESIS:
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
		if (is_a($openUrlSchema, 'OpenUrlJournalBookBaseSchema')) {
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
		if (is_a($openUrlSchema, 'OpenUrlJournalSchema')) {
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
		if (is_a($openUrlSchema, 'OpenUrlBookSchema')) {
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
		if (is_a($openUrlSchema, 'OpenUrlDisertationSchema')) {
			$propertyMap += array(
				'size' => 'tpages',
				'publisher-loc' => 'co',
				'institution' => 'inst'
			);
		}

		return $propertyMap;
	}
}
?>