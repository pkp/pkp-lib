<?php

/**
 * @file plugins/metadata/openurl10/schema/OpenUrl10DissertationSchema.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrl10DissertationSchema
 * @ingroup plugins_metadata_openurl10_schema
 * @see OpenUrl10BaseSchema
 *
 * @brief Class that provides meta-data properties of the
 *  OpenURL 1.0 dissertation standard.
 */


import('lib.pkp.plugins.metadata.openurl10.schema.OpenUrl10BaseSchema');

// "dissertation" is not defined as genre in the standard. We only use it internally.
define('OPENURL10_PSEUDOGENRE_DISSERTATION', 'dissertation');

class OpenUrl10DissertationSchema extends OpenUrl10BaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrl10DissertationSchema() {
		parent::OpenUrl10BaseSchema('openurl-1.0-dissertation');

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