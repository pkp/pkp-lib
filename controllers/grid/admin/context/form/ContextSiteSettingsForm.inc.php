<?php

/**
 * @file controllers/grid/admin/context/form/ContextSiteSettingsForm.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
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
	 * @param $contextId omit for a new context
	 */
	function ContextSiteSettingsForm($contextId = null) {
		parent::Form('admin/contextSettings.tpl');

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
}

?>
