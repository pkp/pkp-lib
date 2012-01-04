<?php

/**
 * @file classes/metadata/NlmCitationSchemaOpenUrlCrosswalkFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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

import('metadata.nlm.OpenUrlCrosswalkFilter');

class NlmCitationSchemaOpenUrlCrosswalkFilter extends OpenUrlCrosswalkFilter {
	/**
	 * Constructor
	 */
	function NlmCitationSchemaOpenUrlCrosswalkFilter() {
		// We transform NLM citation to all types of OpenURL schema
		parent::OpenUrlCrosswalkFilter('NlmCitationSchema', 'OpenUrlBaseSchema');
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
			case NLM_PUBLICATION_TYPE_JOURNAL:
			case NLM_PUBLICATION_TYPE_CONFPROC:
				$outputSchema = new OpenUrlJournalSchema();
				break;

			case NLM_PUBLICATION_TYPE_BOOK:
				$outputSchema = new OpenUrlBookSchema();
				break;

			case NLM_PUBLICATION_TYPE_THESIS:
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

		// Genre: Guesswork
		if (is_a($outputSchema, 'OpenUrlJournalBookBaseSchema')) {
			switch($publicationType) {
				case NLM_PUBLICATION_TYPE_JOURNAL:
					$genre = ($input->hasProperty('article-title') ? OPENURL_GENRE_ARTICLE : OPENURL_GENRE_JOURNAL);
					break;

				case NLM_PUBLICATION_TYPE_CONFPROC:
					$genre = ($input->hasProperty('article-title') ? OPENURL_GENRE_PROCEEDING : OPENURL_GENRE_CONFERENCE);
					break;

				case NLM_PUBLICATION_TYPE_BOOK:
					$genre = ($input->hasProperty('article-title') ? OPENURL_GENRE_BOOKITEM : OPENURL_GENRE_BOOK);
					break;
			}
			assert(!empty($genre));
			$success = $output->addStatement('genre', $genre);
			assert($success);
		}

		// Map remaining properties (NLM => OpenURL)
		$propertyMap =& $this->nlmOpenUrlMapping($publicationType, $outputSchema);

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