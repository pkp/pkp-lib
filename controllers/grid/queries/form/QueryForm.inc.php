<?php

/**
 * @file controllers/grid/users/queries/form/QueryForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryForm
 * @ingroup controllers_grid_users_queries_form
 *
 * @brief Form for adding/editing a new query
 */

import('lib.pkp.classes.form.Form');

class QueryForm extends Form {
	/** @var int ASSOC_TYPE_... */
	var $_assocType;

	/** @var int Assoc ID (per _assocType) */
	var $_assocId;

	/** @var int The stage id associated with the query being edited **/
	var $_stageId;

	/** @var Query The query being edited **/
	var $_query;

	/** @var boolean True iff this is a newly-created query */
	var $_isNew;

	/**
	 * Constructor.
	 * @param $request Request
	 * @param $assocType int ASSOC_TYPE_...
	 * @param $assocId int Assoc ID (per assocType)
	 * @param $stageId int WORKFLOW_STAGE_...
	 * @param $queryId int Optional query ID to edit. If none provided, a
	 *  (potentially temporary) query will be created.
	 */
	function __construct($request, $assocType, $assocId, $stageId, $queryId = null) {
		parent::__construct('controllers/grid/queries/form/queryForm.tpl');
		$this->setStageId($stageId);

		$queryDao = DAORegistry::getDAO('QueryDAO');
		if (!$queryId) {
			$this->_isNew = true;

			// Create a query
			$query = $queryDao->newDataObject();
			$query->setAssocType($assocType);
			$query->setAssocId($assocId);
			$query->setStageId($stageId);
			$query->setSequence(REALLY_BIG_NUMBER);
			$queryDao->insertObject($query);
			$queryDao->resequence($assocType, $assocId);

			// Add the current user as a participant by default.
			$queryDao->insertParticipant($query->getId(), $request->getUser()->getId());

			// Create a head note
			$noteDao = DAORegistry::getDAO('NoteDAO');
			$headNote = $noteDao->newDataObject();
			$headNote->setUserId($request->getUser()->getId());
			$headNote->setAssocType(ASSOC_TYPE_QUERY);
			$headNote->setAssocId($query->getId());
			$headNote->setDateCreated(Core::getCurrentDate());
			$noteDao->insertObject($headNote);
		} else {
			$query = $queryDao->getById($queryId, $assocType, $assocId);
			assert(isset($query));
			// New queries will not have a head note.
			$this->_isNew = !$query->getHeadNote();
		}

		$this->setQuery($query);

		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'users', 'required', 'stageParticipants.notify.warning'));
		$this->addCheck(new FormValidator($this, 'subject', 'required', 'submission.queries.subjectRequired'));
		$this->addCheck(new FormValidator($this, 'comment', 'required', 'submission.queries.messageRequired'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
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
	 * Get assoc type
	 * @return int ASSOC_TYPE_...
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Set assoc type
	 * @param $assocType int ASSOC_TYPE_...
	 */
	function setAssocType($assocType) {
		$this->setData('assocType', $assocType);
	}

	/**
	 * Get assoc id
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * Set assoc id
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		$this->setData('assocId', $assocId);
	}


	//
	// Overridden template methods
	//
	/**
	 * Initialize form data from the associated author.
	 */
	function initData() {
		$queryDao = DAORegistry::getDAO('QueryDAO');
		if ($query = $this->getQuery()) {
			$headNote = $query->getHeadNote();
			$this->_data = array(
				'queryId' => $query->getId(),
				'subject' => $headNote?$headNote->getTitle():null,
				'comment' => $headNote?$headNote->getContents():null,
				'userIds' => $queryDao->getParticipantIds($query->getId()),
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
	 * @param $request PKPRequest
	 * @param $actionArgs array Optional list of additional arguments
	 */
	function fetch($request, $actionArgs = array()) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_EDITOR);

		$query = $this->getQuery();
		$headNote = $query->getHeadNote();
		$user = $request->getUser();

		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$assignments = $stageAssignmentDao->getBySubmissionAndStageId($query->getAssocId(), $query->getStageId());
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$currentParticipants = $query->getId() ? $queryDao->getParticipantIds($query->getId()) : array();
		$userDao = DAORegistry::getDAO('UserDAO');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$participantOptions = array();

		foreach ($assignments->toArray() as $assignment) {
			$userGroup = $userGroupDao->getById($assignment->getUserGroupId());
			$participantOptions[] = array(
				'user' => $userDao->getById($assignment->getUserId()),
				'userGroup' => $userGroup,
				'isParticipant' => in_array($assignment->getUserId(), $currentParticipants),
				'userId' => $assignment->getUserId(),
				'roleId' => $userGroup->getRoleId(),
				'reviewOpen' => false,
			);

			# Check the assignment of the current user
			if ($user->getId() == $assignment->getUserId()){ $userAssignment = $assignment->getUserGroupId(); }
		}

		# In review stage, add reviewers as possible participants
		if ($query->getStageId() == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
			$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
			$reviewAssignments = $reviewAssignmentDao->getBySubmissionId($query->getAssocId());

			foreach ($reviewAssignments as $reviewAssignment) {				

				# Check if current user is a reviewer, and if the used method is blind
				if ($user->getId() == $reviewAssignment->getReviewerId()){ 
					$userAssignment = ROLE_ID_REVIEWER;
					if ($reviewAssignment->getReviewMethod() != SUBMISSION_REVIEW_METHOD_OPEN) { $isBlindReviewer = true; }
				}

				# Check if review is open and confirmed
				$openReview = false;
				if ($reviewAssignment->getReviewMethod() == SUBMISSION_REVIEW_METHOD_OPEN && !$reviewAssignment->getDateConfirmed()) { $openReview = true; }

				# ALEC! userGroup is now hard coded, because reviewAssignment does not have userGroupId!
				# I think it should, because there could be other reviewer roles as well (custom roles)?
				$reviewerUserGroup = $userGroupDao->getByRoleId($request->getContext()->getId(), ROLE_ID_REVIEWER)->toArray();
				$participantOptions[] = array(
					'user' => $userDao->getById($reviewAssignment->getReviewerId()),
					'userGroup' => $reviewerUserGroup[0],
					'isParticipant' => in_array($reviewAssignment->getReviewerId(), $currentParticipants),
					'userId' => $reviewAssignment->getReviewerId(),
					'roleId' => ROLE_ID_REVIEWER,
					'reviewOpen' => $openReview,
				);

			}
			
			// Filter the reviewers depending on the current user and review types
			$filteredParticipantOptions = array();

			foreach ($participantOptions as $participantOption){

				// If current user is an author... 
				if ($userAssignment == ROLE_ID_AUTHOR){
					// ...filter out all reviewers that are not confirmed open reviewers
					if (participantOption['roleId'] == ROLE_ID_REVIEWER && $participantOption['reviewOpen'] != true){ continue; }
										
					// ...leave editorial staff, confirmed open reviewers and authors
					$filteredParticipantOptions[] = $participantOption;
					
				}
				// If current user is a reviewer...
				else if ($userAssignment == ROLE_ID_REVIEWER){
					
					// ... filter out authors, if the current user is a blind reviewer
					if (participantOption['roleId'] == ROLE_ID_AUTHOR && $isBlindReviewer) { continue; }
					
					// ...filter out all reviewers that are not confirmed open reviewers, but never the active reviewer
					if (participantOption['roleId'] == ROLE_ID_REVIEWER && $participantOption['reviewOpen'] != true && $user->getId() != $participantOption['userId']){ continue; }
					
					// ... leave editorial staff, open reviewers
					$filteredParticipantOptions[] = $participantOption;
				}
				// Else the current user has to be an editorial staff that can access the review stage...
				else {
					// ... so filter nothing
					$filteredParticipantOptions[] = $participantOption;
				}
			}

			$participantOptions = $filteredParticipantOptions;

		}

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'isNew' => $this->_isNew,
			'noteId' => $headNote->getId(),
			'actionArgs' => $actionArgs,
			'participantOptions' => $participantOptions,
			'csrfToken' => $request->getSession()->getCSRFToken(),
		));

		return parent::fetch($request);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'subject',
			'comment',
			'users',
		));
	}

	/**
	 * @copydoc Form::execute()
	 * @param $request PKPRequest
	 */
	function execute($request) {
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$query = $this->getQuery();

		$headNote = $query->getHeadNote();
		$headNote->setTitle($this->getData('subject'));
		$headNote->setContents($this->getData('comment'));

		$noteDao = DAORegistry::getDAO('NoteDAO');
		$noteDao->updateObject($headNote);

		$queryDao->updateObject($query);

		// Update participants
		$oldParticipantIds = $queryDao->getParticipantIds($query->getId());
		$newParticipantIds = $this->getData('users');
		$queryDao->removeAllParticipants($query->getId());
		foreach ($newParticipantIds as $userId) {
			$queryDao->insertParticipant($query->getId(), $userId);
		}

		// Update participant notifications
		$notificationManager = new NotificationManager();
		$removed = array_diff($oldParticipantIds, $newParticipantIds);
		$added = array_diff($newParticipantIds, $oldParticipantIds);
		foreach($removed as $userId) {
			// Delete this users's notifications relating to this query
			$notificationDao = DAORegistry::getDAO('NotificationDAO');
			$notificationDao->deleteByAssoc(ASSOC_TYPE_QUERY, $query->getId(), $userId);
		}
		$currentUser = $request->getUser();
		foreach($added as $userId) {
			// Skip sending a message to the current user.
			if ($currentUser->getId() == $userId) {
				continue;
			}
			$notificationManager->createNotification(
				$request,
				$userId,
				NOTIFICATION_TYPE_NEW_QUERY,
				$request->getContext()->getId(),
				ASSOC_TYPE_QUERY,
				$query->getId(),
				NOTIFICATION_LEVEL_TASK
			);
		}
	}
}

?>
