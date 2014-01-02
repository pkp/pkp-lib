<?php

/**
 * @file controllers/grid/eventLog/EventLogGridRow.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EventLogGridRow
 * @ingroup controllers_grid_eventLog
 *
 * @brief EventLog grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class EventLogGridRow extends GridRow {
	/** @var Submission **/
	var $_submission;

	/**
	 * Constructor
	 */
	function EventLogGridRow($submission) {
		$this->_submission = $submission;
		parent::GridRow();
	}
}

?>
