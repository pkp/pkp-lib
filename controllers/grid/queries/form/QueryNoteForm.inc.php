<?php

/**
 * @file controllers/grid/users/queries/form/QueryNoteForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryNoteForm
 * @ingroup controllers_grid_users_queries_form
 *
 * @brief Form for adding/editing a new query note.
 */

import('lib.pkp.classes.form.Form');

class QueryNoteForm extends Form {
	/** @var Submission The submission */
	var $_submission;

	/** @var Query The query to attach the note to */
	var $_query;

	/** @var int Stage ID */
	var $_stageId;

	/**
	 * Constructor.
	 * @param $submission Submission The submission to attach the note to
	 * @param $query Query The query to attach the note to
	 * @param $stageId int The current stage ID
	 */
	function QueryNoteForm($submission, $query, $stageId) {
		parent::Form('controllers/grid/queries/form/queryNoteForm.tpl');
		$this->setQuery($query);
		$this->setSubmission($submission);
		$this->setStageId($stageId);

		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'comment', 'required', 'submission.queries.messageRequired'));
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
	 * Get the submission
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Set the submission
	 * @param @submission Submission
	 */
	function setSubmission($submission) {
		$this->_submission = $submission;
	}

	/**
	 * Set the stage ID
	 * @param $stageId int
	 */
	function setStageId($stageId) {
		$this->_stageId = $stageId;
	}

	/**
	 * Get the stage ID
	 * @return int Stage ID
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'comment',
		));
	}

	/**
	 * @copydoc Form::fetch
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'query' => $this->getQuery(),
			'submission' => $this->getSubmission(),
			'stageId' => $this->getStageId(),
		));
		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 * @param $request PKPRequest
	 * @return Note The created note object.
	 */
	function execute($request) {
		$noteDao = DAORegistry::getDAO('NoteDAO');
		$note = $noteDao->newDataObject();
		$note->setUserId($request->getUser()->getId());
		$note->setAssocType(ASSOC_TYPE_QUERY);
		$note->setAssocId($this->getQuery()->getId());
		$note->setDateCreated(Core::getCurrentDate());
		$note->setDateModified(Core::getCurrentDate());
		$note->setContents($this->getData('comment'));
		$noteDao->insertObject($note);
		return $note;
	}
}

?>
