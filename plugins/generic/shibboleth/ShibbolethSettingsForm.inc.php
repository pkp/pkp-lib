<?php

/**
 * @file plugins/generic/shibboleth/ShibbolethSettingsForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ShibbolethSettingsForm
 * @ingroup plugins_generic_shibboleth
 *
 * @brief Form for journal managers to modify Shibboleth
 * authentication plugin settings
 */

import('lib.pkp.classes.form.Form');

class ShibbolethSettingsForm extends Form {

	/** @var int */
	var $_contextId;

	/** @var object */
	var $_plugin;

	/**
	 * Constructor
	 * @param $plugin ShibbolethAuthPlugin
	 * @param $contextId int
	 */
	function __construct($plugin, $contextId) {
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplatePath() . 'settingsForm.tpl');

		$this->addCheck(
			new FormValidator(
				$this,
				'shibbolethWayfUrl',
				'required',
				'plugins.generic.shibboleth.manager.settings.shibbolethWayfUrlRequired'
			)
		);
		$this->addCheck(
			new FormValidator(
				$this,
				'shibbolethHeaderUin',
				'required',
				'plugins.generic.shibboleth.manager.settings.shibbolethHeaderUinRequired'
			)
		);
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$this->_data = array(
			'shibbolethWayfUrl' => $this->_plugin->getSetting($this->_contextId, 'shibbolethWayfUrl'),
			'shibbolethHeaderUin' => $this->_plugin->getSetting($this->_contextId, 'shibbolethHeaderUin'),
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('shibbolethWayfUrl'));
		$this->readUserVars(array('shibbolethHeaderUin'));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request);
	}

	/**
	 * Save settings.
	 */
	function execute() {
		$this->_plugin->updateSetting(
			$this->_contextId,
			'shibbolethWayfUrl',
			trim($this->getData('shibbolethWayfUrl'), "\"\';"),
			'string'
		);
		$this->_plugin->updateSetting(
			$this->_contextId,
			'shibbolethHeaderUin',
			trim($this->getData('shibbolethHeaderUin'), "\"\';"),
			'string'
		);
	}
}

?>
