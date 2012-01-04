<?php

/**
 * @defgroup metadata_nlm
 */

/**
 * @file classes/metadata/NlmCitationSchema.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchema
 * @ingroup metadata_nlm
 * @see MetadataSchema
 *
 * @brief Class that provides meta-data properties compliant with
 *  the NLM element-citation tag from the NLM Journal Publishing Tag Set
 *  Version 3.0. We only use the "references class" of elements allowed
 *  in the element-citation tag. We do not support all sub-elements
 *  but only those we have use-cases for. We map elements and attributes
 *  from the original XML standard to 'element[@attribute="..."]' property
 *  names.
 *
 *  For details see <http://dtd.nlm.nih.gov/publishing/>,
 *  <http://dtd.nlm.nih.gov/publishing/tag-library/3.0/n-8xa0.html>,
 *  <http://dtd.nlm.nih.gov/publishing/tag-library/3.0/n-5332.html> and
 *  <http://dtd.nlm.nih.gov/publishing/tag-library/3.0/n-fmz0.html>.
 */

import('lib.pkp.classes.metadata.MetadataSchema');

// Define the well-known elements of the NLM publication type vocabulary.
define('NLM_PUBLICATION_TYPE_JOURNAL', 'journal');
define('NLM_PUBLICATION_TYPE_CONFPROC', 'conf-proc');
define('NLM_PUBLICATION_TYPE_BOOK', 'book');
define('NLM_PUBLICATION_TYPE_THESIS', 'thesis');

class NlmCitationSchema extends MetadataSchema {
	/**
	 * Constructor
	 */
	function NlmCitationSchema() {
		$this->setName('nlm-3.0-element-citation');
		$this->setNamespace('nlm30');

		$citation = array(ASSOC_TYPE_CITATION);
		$this->addProperty(new MetadataProperty('person-group[@person-group-type="author"]', $citation, array(array(METADATA_PROPERTY_TYPE_COMPOSITE => ASSOC_TYPE_AUTHOR), METADATA_PROPERTY_TYPE_STRING), false, METADATA_PROPERTY_CARDINALITY_MANY, 'metadata.property.displayName.author', 'metadata.property.validationMessage.author'));
		$this->addProperty(new MetadataProperty('person-group[@person-group-type="editor"]', $citation, array(array(METADATA_PROPERTY_TYPE_COMPOSITE => ASSOC_TYPE_EDITOR), METADATA_PROPERTY_TYPE_STRING), false, METADATA_PROPERTY_CARDINALITY_MANY, 'metadata.property.displayName.editor', 'metadata.property.validationMessage.editor'));
		$this->addProperty(new MetadataProperty('article-title', $citation, METADATA_PROPERTY_TYPE_STRING, true));
		$this->addProperty(new MetadataProperty('source', $citation, METADATA_PROPERTY_TYPE_STRING, true));
		$this->addProperty(new MetadataProperty('date', $citation, METADATA_PROPERTY_TYPE_DATE));
		$this->addProperty(new MetadataProperty('date-in-citation[@content-type="access-date"]', $citation, METADATA_PROPERTY_TYPE_DATE, false, METADATA_PROPERTY_CARDINALITY_ONE, 'metadata.property.displayName.access-date', 'metadata.property.validationMessage.access-date'));
		$this->addProperty(new MetadataProperty('issue', $citation));
		$this->addProperty(new MetadataProperty('volume', $citation));
		$this->addProperty(new MetadataProperty('season', $citation));
		$this->addProperty(new MetadataProperty('chapter-title', $citation, METADATA_PROPERTY_TYPE_STRING, true));
		$this->addProperty(new MetadataProperty('edition', $citation));
		$this->addProperty(new MetadataProperty('series', $citation));
		$this->addProperty(new MetadataProperty('supplement', $citation));
		$this->addProperty(new MetadataProperty('conf-date', $citation, METADATA_PROPERTY_TYPE_DATE));
		$this->addProperty(new MetadataProperty('conf-loc', $citation));
		$this->addProperty(new MetadataProperty('conf-name', $citation));
		$this->addProperty(new MetadataProperty('conf-sponsor', $citation));
		$this->addProperty(new MetadataProperty('institution', $citation));
		$this->addProperty(new MetadataProperty('fpage', $citation, METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('lpage', $citation, METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('size', $citation, METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('publisher-loc', $citation));
		$this->addProperty(new MetadataProperty('publisher-name', $citation));
		$this->addProperty(new MetadataProperty('isbn', $citation));
		$this->addProperty(new MetadataProperty('issn[@pub-type="ppub"]', $citation, METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_ONE, 'metadata.property.displayName.issn', 'metadata.property.validationMessage.issn'));
		$this->addProperty(new MetadataProperty('issn[@pub-type="epub"]', $citation, METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_ONE, 'metadata.property.displayName.eissn', 'metadata.property.validationMessage.eissn'));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="doi"]', $citation, METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_ONE, 'metadata.property.displayName.doi', 'metadata.property.validationMessage.doi'));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="publisher-id"]', $citation, METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_ONE, 'metadata.property.displayName.publisher-id', 'metadata.property.validationMessage.publisher-id'));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="coden"]', $citation, METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_ONE, 'metadata.property.displayName.coden', 'metadata.property.validationMessage.coden'));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="sici"]', $citation, METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_ONE, 'metadata.property.displayName.sici', 'metadata.property.validationMessage.sici'));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="pmid"]', $citation, METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_ONE, 'metadata.property.displayName.pmid', 'metadata.property.validationMessage.pmid'));
		$this->addProperty(new MetadataProperty('uri', $citation, METADATA_PROPERTY_TYPE_URI));
		$this->addProperty(new MetadataProperty('comment', $citation));
		$this->addProperty(new MetadataProperty('annotation', $citation));
		$this->addProperty(new MetadataProperty('[@publication-type]', $citation, array(METADATA_PROPERTY_TYPE_VOCABULARY => 'nlm30-publication-types'), false, METADATA_PROPERTY_CARDINALITY_ONE, 'metadata.property.displayName.publication-type', 'metadata.property.validationMessage.publication-type'));

		// NB: NLM citation does not have very good thesis support. We might
		// encode the degree in the publication type and the advisor as 'contrib'
		// with role 'advisor' in the future.
	}
}
?>