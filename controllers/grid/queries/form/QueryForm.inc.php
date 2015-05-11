<?php

/**
 * @file controllers/grid/users/queries/form/QueryForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryForm
 * @ingroup controllers_grid_users_queries_form
 *
 * @brief Form for adding/editing a new query
 */

import('lib.pkp.classes.form.Form');

class QueryForm extends Form {
	/** The submission associated with the query being edited **/
	var $_submission;

	/** The stage id associated with the query being edited **/
	var $_stageId;

	/** Query the query being edited **/
	var $_query;

	/**
	 * Constructor.
	 * @param $stageId int
	 * @param $query SubmissionFileQuery
	 */
	function QueryForm($submission, $stageId, $query = null) {
		parent::Form('controllers/grid/queries/form/queryForm.tpl');
		$this->setSubmission($submission);
		$this->setStageId($stageId);

		$this->setQuery($query);

		// Validation checks for this form
		$this->addCheck(new FormValidatorListbuilder($this, 'users', 'stageParticipants.notify.warning'));
		$this->addCheck(new FormValidatorLocale($this, 'subject', 'required', 'submission.queries.subjectRequired'));
		$this->addCheck(new FormValidatorLocale($this, 'comment', 'required', 'submission.queries.messageRequired'));
		$this->addCheck(new FormValidatorPost($this));
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the query
	 * @return Query
	 */
	function getQuery() {
		return $this->_query;
	}

	/**
	 * Set the query
	 * @param @query Query
	 */
	function setQuery($query) {
		$this->_query = $query;
	}

	/**
	 * Get the stage id
	 * @return int
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Set the stage id
	 * @param int
	 */
	function setStageId($stageId) {
		$this->_stageId = $stageId;
	}

	/**
	 * Get the Submission
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Set the Submission
	 * @param Submission
	 */
	function setSubmission($submission) {
		$this->_submission = $submission;
	}

	//
	// Overridden template methods
	//
	/**
	 * Initialize form data from the associated author.
	 * @param $query Query
	 */
	function initData() {
		$query = $this->getQuery();

		if ($query) {
			$this->_data = array(
				'queryId' => $query->getId(),
			);
		} else {
			// set intial defaults for queries.
		}
		// in order to be able to use the hook
		return parent::initData();
	}

	/**
	 * Fetch the form.
	 * @see Form::fetch()
	 */
	function fetch($request) {
		$query = $this->getQuery();

		$templateMgr = TemplateManager::getManager($request);

		$router = $request->getRouter();
		$context = $router->getContext($request);

		$submission = $this->getSubmission();
		$templateMgr->assign('submissionId', $submission->getId());
		$templateMgr->assign('stageId', $this->getStageId());

		return parent::fetch($request);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData($request) {
		$this->readUserVars(array(
			'submissionId',
			'stageId',
			'subject',
			'comment',
			'users',
		));

		import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');
		$userData = $this->getData('users');
		ListbuilderHandler::unpack($request, $userData);
	}

	/**
	 * Save query
	 * @see Form::execute()
	 * @return int|null Query ID if query already existed; null otherwise
	 */
	function execute() {
		// in order to be able to use the hook
		parent::execute();
		$query = $this->getQuery();
		if ($query) {
			return $query->getId();
		}
		return null;
	}

	/**
	 * @copydoc ListbuilderHandler::insertEntry()
	 */
	function insertEntry($request, $newRowId) {

		$queryDao = DAORegistry::getDAO('SubmissionFileQueryDAO');
		$submission = $this->getSubmission();
		$query = $this->getQuery();
		$user = $request->getUser();

		if (!$query) {
			// this is a new submission query
			$query = $queryDao->newDataObject();
			$query->setSubmissionId($submission->getId());
			$query->setStageId($this->getStageId());
			$query->setUserId($user->getId());
			$query->setDatePosted(Core::getCurrentDate());
			$query->setParentQueryId(0);
			$existingQuery = false;
		} else {
			$existingQuery = true;
			$query->setDateModified(Core::getCurrentDate());
			if ($submission->getId() !== $query->getSubmissionId()) fatalError('Invalid query!');
		}

		$query->setSubject($this->getData('subject'), null); // localized
		$query->setComment($this->getData('comment'), null); // localized

		if ($existingQuery) {
			$queryDao->updateObject($query);
			$queryId = $query->getId();
		} else {
			$queryId = $queryDao->insertObject($query);
			$query->setId($queryId);
		}

		$this->setQuery($query);

		foreach ($newRowId as $id) {
			$queryDao->insertParticipant($queryId, $id);
		}
	}
}

?>
