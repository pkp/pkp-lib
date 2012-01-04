<?php

/**
 * @file classes/metadata/OpenUrlNlmCitationSchemaCrosswalkFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlNlmCitationSchemaCrosswalkFilter
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

class OpenUrlNlmCitationSchemaCrosswalkFilter extends OpenUrlCrosswalkFilter {
	/**
	 * Constructor
	 */
	function OpenUrlNlmCitationSchemaCrosswalkFilter() {
		// We transform all types of OpenURL schema to NLM citation
		parent::OpenUrlCrosswalkFilter('OpenUrlBaseSchema', 'NlmCitationSchema');
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * Map OpenURL properties to NLM properties.
	 * NB: OpenURL has no i18n so we use the default
	 * locale when mapping.
	 * @see Filter::process()
	 * @param $input MetadataDescription
	 * @return MetadataDescription
	 */
	function &process(&$input) {
		$nullVar = null;

		// Instantiate the target description.
		$outputSchema = new NlmCitationSchema();
		$output = new MetadataDescription($outputSchema, $input->getAssocType());

		// Parse au statements into name descriptions
		import('metadata.nlm.PersonStringNlmNameSchemaFilter');
		$personStringFilter = new PersonStringNlmNameSchemaFilter(ASSOC_TYPE_AUTHOR);
		$authors =& $input->getStatement('au');
		if (is_array($authors) && count($authors)) {
			// TODO: We might improve results here by constructing the
			// first author from aufirst, aulast fields.
			foreach ($authors as $author) {
				$authorDescription =& $personStringFilter->execute($author);
				$success = $output->addStatement('person-group[@person-group-type="author"]', $authorDescription);
				assert($success);
				unset($authorDescription);
			}
		}

		// Publication type
		if ($input->hasStatement('genre')) {
			$genre = $input->getStatement('genre');
			$genreMap = $this->_getOpenUrlGenreTranslationMapping();
			$publicationType = (isset($genreMap[$genre]) ? $genreMap[$genre] : $genre);
			$success = $output->addStatement('[@publication-type]', $publicationType);
			assert($success);
		}

		// Get NLM => OpenURL property mapping.
		$propertyMap =& $this->nlmOpenUrlMapping($publicationType, $input->getMetadataSchema());

		// Transfer mapped properties with default locale
		foreach ($propertyMap as $nlmProperty => $openUrlProperty) {
			if ($input->hasStatement($openUrlProperty)) {
				$success = $output->addStatement($nlmProperty, $input->getStatement($openUrlProperty));
				assert($success);
			}
		}

		return $output;
	}

	//
	// Private helper methods
	//
	/**
	 * Return a mapping of OpenURL genres to NLM publication
	 * types.
	 * NB: PHP4 work-around for a private static class member
	 * FIXME: Implement this with an OpenURL-to-NLM crosswalk
	 * filter.
	 * @return array
	 */
	function _getOpenUrlGenreTranslationMapping() {
		static $openUrlGenreTranslationMapping = array(
			OPENURL_GENRE_ARTICLE => NLM_PUBLICATION_TYPE_JOURNAL,
			OPENURL_GENRE_ISSUE => NLM_PUBLICATION_TYPE_JOURNAL,
			OPENURL_GENRE_CONFERENCE => NLM_PUBLICATION_TYPE_CONFPROC,
			OPENURL_GENRE_PROCEEDING => NLM_PUBLICATION_TYPE_CONFPROC,
			OPENURL_GENRE_PREPRINT => NLM_PUBLICATION_TYPE_JOURNAL,
			OPENURL_GENRE_BOOKITEM => NLM_PUBLICATION_TYPE_BOOK,
			OPENURL_GENRE_BOOK => NLM_PUBLICATION_TYPE_BOOK,
			OPENURL_GENRE_REPORT => NLM_PUBLICATION_TYPE_BOOK,
			OPENURL_GENRE_DOCUMENT => NLM_PUBLICATION_TYPE_BOOK,
			OPENURL_PSEUDOGENRE_DISSERTATION => NLM_PUBLICATION_TYPE_THESIS
		);

		return $openUrlGenreTranslationMapping;
	}
}
?>