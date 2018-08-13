<?php

/**
 * @file classes/user/form/RolesForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPProfileForm
 * @ingroup user_form
 *
 * @brief Form to edit the roles area of the user profile.
 */

import('lib.pkp.classes.user.form.BaseProfileForm');

class RolesForm extends BaseProfileForm {

	/**
	 * Constructor.
	 * @param $template string
	 * @param $user PKPUser
	 */
	function __construct($user) {
		parent::__construct('user/rolesForm.tpl', $user);

		// Validation checks for this form
	}

	/**
	 * @copydoc BaseProfileForm::fetch
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO');
		$userGroupAssignments = $userGroupAssignmentDao->getByUserId($request->getUser()->getId());
		$userGroupIds = array();
		while ($assignment = $userGroupAssignments->next()) {
			$userGroupIds[] = $assignment->getUserGroupId();
		}
		$templateMgr->assign('userGroupIds', $userGroupIds);

		import('lib.pkp.classes.user.form.UserFormHelper');
		$userFormHelper = new UserFormHelper();
		$userFormHelper->assignRoleContent($templateMgr, $request);

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER);

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc BaseProfileForm::initData()
	 */
	function initData() {
		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();

		$user = $this->getUser();

		$this->_data = array(
			'interests' => $interestManager->getInterestsForUser($user),
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		parent::readInputData();

		$this->readUserVars(array(
			'authorGroup',
			'reviewerGroup',
			'readerGroup',
			'interests',
		));
	}

	/**
	 * Save roles settings.
	 * @param $request PKPRequest
	 */
	function execute($request) {
		$user = $request->getUser();

		// Save the roles
		import('lib.pkp.classes.user.form.UserFormHelper');
		$userFormHelper = new UserFormHelper();
		$userFormHelper->saveRoleContent($this, $user);

		// Insert the user interests
		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();
		$interestManager->setInterestsForUser($user, $this->getData('interests'));

		parent::execute($request, $user);
	}
}

?>
