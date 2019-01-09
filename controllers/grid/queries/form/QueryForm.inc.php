<?php

/**
 * @file controllers/grid/users/queries/form/QueryForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
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
		$this->addCheck(new FormValidatorCustom($this, 'users', 'required', 'stageParticipants.notify.warning', function($users) {
			return count($users) > 1;
		}));
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
	function fetch($request, $template = null, $display = false, $actionArgs = array()) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_EDITOR);

		$query = $this->getQuery();
		$headNote = $query->getHeadNote();
		$user = $request->getUser();
		$context = $request->getContext();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'isNew' => $this->_isNew,
			'noteId' => $headNote->getId(),
			'actionArgs' => $actionArgs,
			'csrfToken' => $request->getSession()->getCSRFToken(),
		));

		// Queryies only support ASSOC_TYPE_SUBMISSION so far
		if ($query->getAssocType() == ASSOC_TYPE_SUBMISSION) {

			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');

			// Get currently selected participants in the query
			$queryDao = DAORegistry::getDAO('QueryDAO');
			$selectedParticipants = $query->getId() ? $queryDao->getParticipantIds($query->getId()) : array();

			// Always include current user, even if not with a stage assignment
			$includeUsers[] = $user->getId();
			$excludeUsers = null;

			// When in review stage, include/exclude users depending on the current users role
			$reviewAssignments = array();
			if ($query->getStageId() == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW || $query->getStageId() == WORKFLOW_STAGE_ID_INTERNAL_REVIEW) {

				// Get all review assignments for current submission
				$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
				$reviewAssignments = $reviewAssignmentDao->getBySubmissionId($query->getAssocId());

				// Get current users roles
				$userRoles = array();
				$usersAssignments = $stageAssignmentDao->getBySubmissionAndStageId($query->getAssocId(), $query->getStageId(), null, $user->getId());
				while ($usersAssignment = $usersAssignments->next()) {
					$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
					$userGroup = $userGroupDao->getById($usersAssignment->getUserGroupId());
					$userRoles[] = $userGroup->getRoleId();
				}

				// if current user is editor, add all reviewers
				if ( $user->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN), $context->getId()) || array_intersect(array(ROLE_ID_SUB_EDITOR), $userRoles) ) {
					foreach ($reviewAssignments as $reviewAssignment) {
						$includeUsers[] = $reviewAssignment->getReviewerId();
					}
				}

				// if current user is blind reviewer, filter out authors
				foreach ($reviewAssignments as $reviewAssignment) {
					if ($reviewAssignment->getReviewerId() === $user->getId()){
						if ($reviewAssignment->getReviewMethod() != SUBMISSION_REVIEW_METHOD_OPEN){
							$authorAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($query->getAssocId(), ROLE_ID_AUTHOR);
							while ($assignment = $authorAssignments->next()) {
								$excludeUsers[] = $assignment->getUserId();
							}
						}
					}
				}

				// if current user is author, add open reviewers who have accepted the request
				if (array_intersect(array(ROLE_ID_AUTHOR), $userRoles)) {
					foreach ($reviewAssignments as $reviewAssignment) {
						if ($reviewAssignment->getReviewMethod() == SUBMISSION_REVIEW_METHOD_OPEN && $reviewAssignment->getDateConfirmed()){
							$includeUsers[] = $reviewAssignment->getReviewerId();
						}
					}
				}
			}

			import('lib.pkp.classes.components.listPanels.users.SelectUserListPanel');
			$queryParticipantsList = new SelectUserListPanel(array(
				'title' => 'editor.submission.stageParticipants',
				'inputName' => 'users[]',
				'selected' => $selectedParticipants,
				'getParams' => array(
					'count' => 100, // high upper value
					'offset' => 0,
					'assignedToSubmission' => $query->getAssocId(),
					'assignedToSubmissionStage' => $query->getStageId(),
					'includeUsers' => $includeUsers,
					'excludeUsers' => $excludeUsers,
				),
				// Include the full name and role in this submission in the item title
				'setItemTitleCallback' => function($user, $userProps) use ($stageAssignmentDao, $reviewAssignments, $query) {
					$title = $user->getFullName();
					$userRoles = array();
					$usersAssignments = $stageAssignmentDao->getBySubmissionAndStageId($query->getAssocId(), $query->getStageId(), null, $user->getId())->toArray();
					foreach ($usersAssignments as $assignment) {
						foreach ($userProps['groups'] as $userGroup) {
							if ($userGroup['id'] === (int) $assignment->getUserGroupId() && isset($userGroup['name'][AppLocale::getLocale()])) {
								$userRoles[] = $userGroup['name'][AppLocale::getLocale()];
							}
						}
					}
					foreach ($reviewAssignments as $assignment) {
						if ($assignment->getReviewerId() === $user->getId()) {
							$userRoles[] =  __('user.role.reviewer') . " (" . __($assignment->getReviewMethodKey()) . ")";
						}
					}
					$title =  __('submission.query.participantTitle', array(
								'fullName' => $user->getFullName(),
								'userGroup' => join(__('common.commaListSeparator'), $userRoles),
					));
					return $title;
				},
			));

			$queryParticipantsListData = $queryParticipantsList->getConfig();

			$templateMgr->assign(array(
				'hasParticipants' => count($queryParticipantsListData['items']),
				'queryParticipantsListData' => $queryParticipantsListData,
			));
		}

		return parent::fetch($request, $template, $display);
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
	 */
	function execute() {
		$request = Application::getRequest();
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
