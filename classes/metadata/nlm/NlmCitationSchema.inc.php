<?php

/**
 * @file classes/metadata/NlmCitationSchema.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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

// $Id$

import('metadata.MetadataSchema');

class NlmCitationSchema extends MetadataSchema {
	/**
	 * Constructor
	 */
	function NlmCitationSchema() {
		$this->setName('nlm-3.0-element-citation');
		$this->setNamespace('nlm30');

		$citation = array(ASSOC_TYPE_CITATION);
		$this->addProperty(new MetadataProperty('person-group[@person-group-type="author"]', $citation, METADATA_PROPERTY_TYPE_COMPOSITE, false, METADATA_PROPERTY_CARDINALITY_MANY, ASSOC_TYPE_AUTHOR));
		$this->addProperty(new MetadataProperty('person-group[@person-group-type="editor"]', $citation, METADATA_PROPERTY_TYPE_COMPOSITE, false, METADATA_PROPERTY_CARDINALITY_MANY, ASSOC_TYPE_EDITOR));
		$this->addProperty(new MetadataProperty('article-title', $citation, METADATA_PROPERTY_TYPE_STRING, true));
		$this->addProperty(new MetadataProperty('source', $citation, METADATA_PROPERTY_TYPE_STRING, true));
		$this->addProperty(new MetadataProperty('date', $citation, METADATA_PROPERTY_TYPE_DATE));
		$this->addProperty(new MetadataProperty('date-in-citation[@content-type="access-date"]', $citation, METADATA_PROPERTY_TYPE_DATE));
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
		$this->addProperty(new MetadataProperty('issn[@pub-type="ppub"]', $citation));
		$this->addProperty(new MetadataProperty('issn[@pub-type="epub"]', $citation));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="doi"]', $citation));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="publisher-id"]', $citation));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="coden"]', $citation));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="sici"]', $citation));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="pmid"]', $citation));
		$this->addProperty(new MetadataProperty('uri', $citation, METADATA_PROPERTY_TYPE_URI));
		$this->addProperty(new MetadataProperty('comment', $citation));
		$this->addProperty(new MetadataProperty('annotation', $citation));
		$this->addProperty(new MetadataProperty('[@publication-type]', $citation)); // FIXME: implement as controlled vocabulary

		// NB: NLM citation does not have very good thesis support. We might
		// encode the degree in the publication type and the advisor as 'contrib'
		// with role 'advisor' in the future.
	}
}
?>