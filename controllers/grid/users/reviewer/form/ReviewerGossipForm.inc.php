<?php

/**
 * @file controllers/grid/users/reviewer/form/ReviewerGossipForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerGossipForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for viewing and editing gossip about a reviewer
 */

import('lib.pkp.classes.form.Form');

class ReviewerGossipForm extends Form {

	/** @var User The user to gossip about */
	var $_user;

	/** @var array Arguments used to route the form op */
	var $_requestArgs;

	/**
	 * Constructor.
	 * @param $user User The user to gossip about
	 * @param $requestArgs array Arguments used to route the form op to the
	 *  correct submission, stage and review round
	 */
	function __construct($user, $requestArgs) {
		parent::__construct('controllers/grid/users/reviewer/form/reviewerGossipForm.tpl');
		$this->_user = $user;
		$this->_requestArgs = $requestArgs;
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'gossip',
		));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'requestArgs' => $this->_requestArgs,
			'gossip' => $this->_user->getGossip(),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$this->_user->setGossip($this->getData('gossip'));
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		$userDao->updateObject($this->_user);
		parent::execute(...$functionArgs);
	}
}


