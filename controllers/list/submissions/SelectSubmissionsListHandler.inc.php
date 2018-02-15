<?php
/**
 * @file classes/controllers/list/submissions/SelectSubmissionsListHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectSubmissionsListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A list handler for selecting submissions
 */
import('controllers.list.submissions.SubmissionsListHandler');

class SelectSubmissionsListHandler extends SubmissionsListHandler {

	/** @var int Name to use for the checkbox input field when selecting submissions */
	public $_inputName = 'selectedSubmissions';

	/**
	 * @copydoc SubmissionsListHandler::init()
	 */
	public function init( $args = array() ) {
		parent::init($args);
		$this->_inputName = isset($args['inputName']) ? $args['inputName'] : $this->_inputName;
	}

	/**
	 * @copydoc SubmissionsListHandler::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['inputName'] = $this->_inputName;
		$config['i18n']['viewSubmission'] = __('submission.list.viewSubmission');
		return $config;
	}
}
