<?php

/**
 * @file classes/citation/CitationLookupService.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationLookupService
 * @ingroup citation
 * @see CitationMangager
 *
 * @brief Base class for citation lookup services implementations.
 */

// $Id$

import('citation.CitationService');

class CitationLookupService extends CitationService {
	/** @var array an array of citation genres that can be processed - set by sub-classes */
	var $_supportedGenres = array();
	
	/**
	 * Take in a Citation in state CITATION_PARSED and
	 * return a citation in state CITATION_LOOKED_UP.
	 * @param $citation Citation the citation object to be looked up.
	 * @return Citation a looked up citation
	 */
	function &lookup(&$citation) {
		// to be implemented by sub-classes
		assert(false);
	}
	
	/**
	 * Checks whether a given citation can be looked-up
	 * by this citation lookup service.
	 * @param $citation Citation
	 * @return boolean true, if supported, otherwise false
	 */
	function supports(&$citation) {
		return in_array($citation->getGenre(), $this->_supportedGenres);
	}
}
?>