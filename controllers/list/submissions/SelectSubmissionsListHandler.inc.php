<?php
/**
 * @file classes/controllers/list/submissions/SelectSubmissionsListHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectSubmissionsListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A list handler for selecting submissions
 */
import('lib.pkp.controllers.list.submissions.SubmissionsListHandler');

class SelectSubmissionsListHandler extends SubmissionsListHandler {

	/**
	 * Name to use for the checkbox input field when selecting submissions
	 *
	 * @param int
	 */
	public $_inputName = 'selectedSubmissions';

	/**
	 * @see SubmissionsListHandler
	 */
	public function init( $args = array() ) {
		parent::init($args);
		$this->_inputName = isset($args['inputName']) ? $args['inputName'] : $this->_inputName;
	}

	/**
	 * @see SubmissionsListHandler
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['inputName'] = $this->_inputName;
		return $config;
	}
}
