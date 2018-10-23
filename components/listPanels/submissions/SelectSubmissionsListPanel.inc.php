<?php
/**
 * @file classes/components/listPanelssubmissions/SelectSubmissionsListPanel.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectSubmissionsListPanel
 * @ingroup classes_controllers_list
 *
 * @brief A list handler for selecting submissions
 */
import('components.listPanels.submissions.SubmissionsListPanel');

class SelectSubmissionsListPanel extends SubmissionsListPanel {

	/** @var int Name to use for the checkbox input field when selecting submissions */
	public $_inputName = 'selectedSubmissions';

	/**
	 * @copydoc SubmissionsListPanel::init()
	 */
	public function init( $args = array() ) {
		parent::init($args);
		$this->_inputName = isset($args['inputName']) ? $args['inputName'] : $this->_inputName;
	}

	/**
	 * @copydoc SubmissionsListPanel::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['inputName'] = $this->_inputName;
		$config['i18n']['viewSubmission'] = __('submission.list.viewSubmission');
		return $config;
	}
}
