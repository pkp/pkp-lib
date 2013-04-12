<?php

/**
 * @file pages/submission/PKPSubmissionHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionHandler
 * @ingroup pages_submission
 *
 * @brief Base handler for submission requests.
 */

import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');

class PKPSubmissionHandler extends Handler {
	/**
	 * Constructor
	 */
	function PKPSubmissionHandler() {
		parent::Handler();
	}
}

?>
