<?php

/**
 * @file classes/file/wrappers/ResourceWrapper.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ResourceWrapper
 * @ingroup file_wrappers
 *
 * @brief Class abstracting operations for accessing resources.
 */

class ResourceWrapper extends FileWrapper {
	/**
	 * Constructor.
	 * @param $url string
	 * @param $info array
	 */
	function ResourceWrapper(&$fp) {
		$this->fp =& $fp;
	}

	function open($mode = 'r') {
		// The resource should already be open
		return true;
	}
}

?>
