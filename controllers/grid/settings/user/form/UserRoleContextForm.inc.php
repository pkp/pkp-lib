<?php

/**
 * @file controllers/grid/settings/user/form/UserRoleContextForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserRoleContextForm
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Form for selecting a context during the edit user workflow, so that
 *  the UserRoleForm knows which contexts to load group assignments for.
 */

import('lib.pkp.classes.form.Form');

class UserRoleContextForm extends Form {

	/** @var Id of the user being edited */
	var $userId;

	/**
	 * Constructor.
	 * @param int $userId
	 */
	function __construct($userId) {
		parent::__construct('controllers/grid/settings/user/form/userRoleContextForm.tpl');

		$this->userId = $userId;
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
		$this->addCheck(new FormValidator($this, 'contextId', 'required', 'manager.people.selectContextForRole.required'));
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	public function readInputData() {
		$this->readUserVars(array('contextId'));
		parent::readInputData();
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request = null, $template = null, $display = false) {

		if (!$request) {
			$request = Application::getRequest();
		}

		$contextDao = Application::getContextDAO();
		$contextsResult = $contextDao->getAll();
		$contexts = array();
		while ($context = $contextsResult->next()) {
			$contexts[] = array(
				'id' => (int) $context->getId(),
				'title' => $context->getLocalizedName(),
			);
		}

		import('lib.pkp.controllers.list.SelectListHandler');
		$selectContextHandler = new SelectListHandler(array(
			'title' => 'manager.people.selectContextForRole',
			'inputName' => 'contextId',
			'inputType' => 'radio',
			'selected' => $this->getData('contextIds'),
			'items' => $contexts,
		));

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'userId' => $this->userId,
			'selectContextListData' => json_encode($selectContextHandler->getConfig()),
		));

		return parent::fetch($request, $template, $display);
	}
}

?>
