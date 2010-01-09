<?php

/**
 * @file classes/metadata/NlmCitationSchema.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchema
 * @ingroup metadata
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
		$this->addProperty(new MetadataProperty('person-group[@person-group-type="author"]', METADATA_PROPERTY_TYPE_COMPOSITE));
		$this->addProperty(new MetadataProperty('person-group[@person-group-type="editor"]', METADATA_PROPERTY_TYPE_COMPOSITE));
		$this->addProperty(new MetadataProperty('article-title', METADATA_PROPERTY_TYPE_STRING, true));
		$this->addProperty(new MetadataProperty('source', METADATA_PROPERTY_TYPE_STRING, true));
		$this->addProperty(new MetadataProperty('date', METADATA_PROPERTY_TYPE_DATE));
		$this->addProperty(new MetadataProperty('date-in-citation[@content-type="access-date"]', METADATA_PROPERTY_TYPE_DATE));
		$this->addProperty(new MetadataProperty('issue'));
		$this->addProperty(new MetadataProperty('volume'));
		$this->addProperty(new MetadataProperty('season'));
		$this->addProperty(new MetadataProperty('chapter-title', METADATA_PROPERTY_TYPE_STRING, true));
		$this->addProperty(new MetadataProperty('edition'));
		$this->addProperty(new MetadataProperty('series'));
		$this->addProperty(new MetadataProperty('supplement'));
		$this->addProperty(new MetadataProperty('conf-date', METADATA_PROPERTY_TYPE_DATE));
		$this->addProperty(new MetadataProperty('conf-loc'));
		$this->addProperty(new MetadataProperty('conf-name'));
		$this->addProperty(new MetadataProperty('conf-sponsor'));
		$this->addProperty(new MetadataProperty('fpage', METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('lpage', METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('size', METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('publisher-loc'));
		$this->addProperty(new MetadataProperty('publisher-name'));
		$this->addProperty(new MetadataProperty('isbn'));
		$this->addProperty(new MetadataProperty('issn[@pub-type="ppub"]'));
		$this->addProperty(new MetadataProperty('issn[@pub-type="epub"]'));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="doi"]'));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="publisher-id"]'));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="coden"]'));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="sici"]'));
		$this->addProperty(new MetadataProperty('pub-id[@pub-id-type="pmid"]'));
		$this->addProperty(new MetadataProperty('uri', METADATA_PROPERTY_TYPE_URI));
		$this->addProperty(new MetadataProperty('comment'));
		$this->addProperty(new MetadataProperty('annotation'));
		$this->addProperty(new MetadataProperty('[@publication-type]'));
	}
}
?>