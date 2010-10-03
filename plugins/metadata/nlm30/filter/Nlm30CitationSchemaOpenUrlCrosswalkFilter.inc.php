<?php

/**
 * @file plugins/metadata/nlm30/Nlm30CitationSchemaOpenUrlCrosswalkFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Nlm30CitationSchemaOpenUrlCrosswalkFilter
 * @ingroup metadata_nlm
 * @see Nlm30CitationSchema
 * @see OpenUrlBookSchema
 * @see OpenUrlJournalSchema
 * @see OpenUrlDissertationSchema
 *
 * @brief Filter that converts from NLM citation to
 *  OpenURL schemas.
 */

import('lib.pkp.plugins.metadata.nlm30.filter.OpenUrlCrosswalkFilter');

class Nlm30CitationSchemaOpenUrlCrosswalkFilter extends OpenUrlCrosswalkFilter {
	/**
	 * Constructor
	 */
	function Nlm30CitationSchemaOpenUrlCrosswalkFilter() {
		$this->setDisplayName('Crosswalk from NLM Citation to Open URL');
		parent::OpenUrlCrosswalkFilter('lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema',
				'lib.pkp.plugins.metadata.openurl10.schema.OpenUrlBaseSchema');
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
				$outputSchemaName = 'lib.pkp.plugins.metadata.openurl10.schema.OpenUrlJournalSchema';
				break;

			case NLM_PUBLICATION_TYPE_BOOK:
				$outputSchemaName = 'lib.pkp.plugins.metadata.openurl10.schema.OpenUrlBookSchema';
				break;

			case NLM_PUBLICATION_TYPE_THESIS:
				$outputSchemaName = 'lib.pkp.plugins.metadata.openurl10.schema.OpenUrlDissertationSchema';
				break;

			default:
				// Unsupported type
				return $nullVar;
		}

		// Create the target description
		$output = new MetadataDescription($outputSchemaName, $input->getAssocType());

		// Transform authors
		import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30NameSchemaPersonStringFilter');
		$personStringFilter = new Nlm30NameSchemaPersonStringFilter();
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
		if (is_a($output->getMetadataSchema(), 'OpenUrlJournalBookBaseSchema')) {
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
		$propertyMap =& $this->nlmOpenUrlMapping($publicationType, $output->getMetadataSchema());

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