<?php

/**
 * @file controllers/grid/admin/context/form/ContextSiteSettingsForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ContextSiteSettingsForm
 * @ingroup controllers_grid_admin_context_form
 *
 * @brief Form for site administrator to edit basic context settings.
 */

import('lib.pkp.classes.db.DBDataXMLParser');
import('lib.pkp.classes.form.Form');

class ContextSiteSettingsForm extends Form {

	/** The ID of the context being edited */
	var $contextId;

	/**
	 * Constructor.
	 * @param $template string
	 * @param $contextId omit for a new context
	 */
	function ContextSiteSettingsForm($template, $contextId = null) {
		parent::Form($template);

		$this->contextId = isset($contextId) ? (int) $contextId : null;

		// Validation checks for this form
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Display the form.
	 */
	function fetch($args, $request) {
		$json = new JSONMessage();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('contextId', $this->contextId);

		return parent::fetch($request);
	}

	/**
	 * Initialize form data from current settings.
	 * @param $context Context optional
	 */
	function initData($context = null) {
		if ($context) {
			$this->setData('name', $context->getName(null));
			$this->setData('description', $context->getDescription(null));
			$this->setData('path', $context->getPath());
			$this->setData('enabled', $context->getEnabled());
		} else {
			$this->setData('enabled', 1);
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('name', 'description', 'path', 'enabled'));
	}

	/**
	 * Get a list of field names for which localized settings are used
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('name', 'description');
	}

	/**
	 * Initially populate the user groups and assignments when creating a new context.
	 * @param $contextId int
	 */
	function _loadDefaultUserGroups($contextId) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_DEFAULT, LOCALE_COMPONENT_PKP_DEFAULT);
		// Install default user groups
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroupDao->installSettings($contextId, 'registry/userGroups.xml');
	}

	/**
	 * Make the site administrator the manager of the newly created context.
	 * @param $contextId int
	 */
	function _assignManagerGroup($contextId) {
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$sessionManager = SessionManager::getManager();
		$userSession = $sessionManager->getUserSession();
		if ($userSession->getUserId() != null && $userSession->getUserId() != 0 && !empty($contextId)) {
			// get the default site admin user group
			$managerUserGroup = $userGroupDao->getDefaultByRoleId($contextId, ROLE_ID_MANAGER);
			$userGroupDao->assignUserToGroup($userSession->getUserId(), $managerUserGroup->getId());
		}
	}
}

?>
