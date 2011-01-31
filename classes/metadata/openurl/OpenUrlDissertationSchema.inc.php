<?php

/**
 * @file classes/metadata/openurl/OpenUrlDissertationSchema.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlDissertationSchema
 * @ingroup metadata_openurl
 * @see OpenUrlBaseSchema
 *
 * @brief Class that provides meta-data properties of the
 *  OpenURL 1.0 dissertation standard.
 */


import('lib.pkp.classes.metadata.openurl.OpenUrlBaseSchema');

// "dissertation" is not defined as genre in the standard. We only use it internally.
define('OPENURL_PSEUDOGENRE_DISSERTATION', 'dissertation');

class OpenUrlDissertationSchema extends OpenUrlBaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrlDissertationSchema() {
		parent::OpenUrlBaseSchema('openurl-1.0-dissertation');

		// Add meta-data properties that only appear in the OpenURL dissertation standard
		$this->addProperty('co'); // Country of publication (plain text)
		$this->addProperty('cc'); // Country of publication (ISO 2-character code)
		$this->addProperty('inst'); // Institution that issued the dissertation
		$this->addProperty('advisor');
		$this->addProperty('tpages', METADATA_PROPERTY_TYPE_INTEGER);
		$this->addProperty('degree');
	}
}
?>