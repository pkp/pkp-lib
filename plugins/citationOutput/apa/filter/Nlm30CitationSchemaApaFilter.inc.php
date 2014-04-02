<?php

/**
 * @defgroup plugins_citationOutput_apa_filter
 */

/**
 * @file plugins/citationOutput/apa/filter/Nlm30CitationSchemaApaFilter.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Nlm30CitationSchemaApaFilter
 * @ingroup plugins_citationOutput_apa_filter
 *
 * @brief Filter that transforms NLM citation metadata descriptions into
 *  APA citation output.
 */

import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaCitationOutputFormatFilter');

class Nlm30CitationSchemaApaFilter extends Nlm30CitationSchemaCitationOutputFormatFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function Nlm30CitationSchemaApaFilter(&$filterGroup) {
		$this->setDisplayName('APA Citation Output');

		parent::Nlm30CitationSchemaCitationOutputFormatFilter($filterGroup);
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @see PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.citationOutput.apa.filter.Nlm30CitationSchemaApaFilter';
	}


	//
	// Implement abstract template methods from TemplateBasedFilter
	//
	/**
	 * @see TemplateBasedFilter::getBasePath()
	 */
	function getBasePath() {
		return dirname(__FILE__);
	}
}
?>
