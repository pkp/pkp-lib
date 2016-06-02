<?php

/**
 * @file controllers/tab/pubIds/form/PKPPublicIdentifiersForm.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicIdentifiersForm
 * @ingroup controllers_tab_pubIds_form
 *
 * @brief Displays a pub ids form.
 */

import('lib.pkp.classes.form.Form');
import('lib.pkp.classes.plugins.PKPPubIdPluginHelper');

class PKPPublicIdentifiersForm extends Form {

	/** @var int The context id */
	var $_contextId;

	/** @var object The pub object the identifiers are edited of
	 * OJS Issue, Article, Representation or SubmissionFile
	 */
	var $_pubObject;

	/** @var int The current stage id, WORKFLOW_STAGE_ID_ */
	var $_stageId;

	/**
	 * @var array Parameters to configure the form template.
	 */
	var $_formParams;

	/**
	 * Constructor.
	 * @param $template string Form template path
	 * @param $pubObject object
	 * @param $stageId integer
	 * @param $formParams array
	 */
	function PKPPublicIdentifiersForm($template, $pubObject, $stageId = null, $formParams = null) {
		parent::Form($template);

		$this->_pubObject = $pubObject;
		$this->_stageId = $stageId;
		$this->_formParams = $formParams;

		$request = Application::getRequest();
		$context = $request->getContext();
		$this->_contextId = $context->getId();

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);

		$this->addCheck(new FormValidatorPost($this));

		// action links for pub id reset requests
		import('lib.pkp.classes.plugins.PKPPubIdPluginHelper');
		$pubIdPluginHelper = new PKPPubIdPluginHelper();
		$pubIdPluginHelper->setLinkActions($this->getContextId(), $this, $pubObject);
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $this->getContextId());
		$templateMgr->assign('pubIdPlugins', $pubIdPlugins);
		$templateMgr->assign('pubObject', $this->getPubObject());
		$templateMgr->assign('stageId', $this->getStageId());
		$templateMgr->assign('formParams', $this->getFormParams());
		return parent::fetch($request);
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		$pubObject = $this->getPubObject();
		$this->setData('publisherId', $pubObject->getStoredPubId('publisher-id'));
		$pubIdPluginHelper = new PKPPubIdPluginHelper();
		$pubIdPluginHelper->init($this->getContextId(), $this, $pubObject);
	}


	//
	// Getters
	//
	/**
	 * Get the pub object
	 * @return object
	 */
	function getPubObject() {
		return $this->_pubObject;
	}

	/**
	 * Get the stage id
	 * @return integer WORKFLOW_STAGE_ID_
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Get the context id
	 * @return integer
	 */
	function getContextId() {
		return $this->_contextId;
	}

	/**
	 * Get the extra form parameters.
	 * @return array
	 */
	function getFormParams() {
		return $this->_formParams;
	}


	//
	// Form methods
	//
	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('publisherId'));
		$pubIdPluginHelper = new PKPPubIdPluginHelper();
		$pubIdPluginHelper->readInputData($this->getContextId(), $this);
	}

	/**
	 * @copydoc Form::validate()
	 */
	function validate() {
		$pubIdPluginHelper = new PKPPubIdPluginHelper();
		$pubIdPluginHelper->validate($this->getContextId(), $this, $this->getPubObject());
		return parent::validate();
	}

	/**
	 * Store objects with pub ids.
	 * @copydoc Form::execute()
	 */
	function execute($request) {
		parent::execute($request);

		$pubObject = $this->getPubObject();
		$pubObject->setStoredPubId('publisher-id', $this->getData('publisherId'));

		$pubIdPluginHelper = new PKPPubIdPluginHelper();
		$pubIdPluginHelper->execute($this->getContextId(), $this, $pubObject);

		if (is_a($pubObject, 'Submission')) {
			$submissionDao = Application::getSubmissionDAO();
			$submissionDao->updateObject($pubObject);
		} elseif (is_a($pubObject, 'Representation')) {
			$representationDao = Application::getRepresentationDAO();
			$representationDao->updateObject($pubObject);
		} elseif (is_a($pubObject, 'SubmissionFile')) {
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
			$submissionFileDao->updateObject($pubObject);
		}
	}

	/**
	 * Clear pub id.
	 * @param $pubIdPlugInClassName string
	 */
	function clearPubId($pubIdPlugInClassName) {
		$pubIdPluginHelper = new PKPPubIdPluginHelper();
		$pubIdPluginHelper->clearPubId($this->getContextId(), $pubIdPlugInClassName, $this->getPubObject());
	}

}

?>
