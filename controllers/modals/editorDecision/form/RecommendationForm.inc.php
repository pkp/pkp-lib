<?php

/**
 * @file controllers/modals/editorDecision/form/RecommendationForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RecommendationForm
 * @ingroup controllers_modals_editorDecision_form
 *
 * @brief Editor recommendation form.
 */

import('lib.pkp.classes.form.Form');

// Define review round and review stage id constants.
import('lib.pkp.classes.submission.reviewRound.ReviewRound');

class RecommendationForm extends Form {
	/** @var Submission The submission associated with the editor recommendation */
	var $_submission;

	/** @var integer The stage ID where the recommendation is being made */
	var $_stageId;

	/** @var ReviewRound */
	var $_reviewRound;

	/**
	 * Constructor.
	 * @param $submission Submission
	 * @param $stageId integer
	 * @param $reviewRound ReviewRound
	 */
	function __construct($submission, $stageId, $reviewRound) {
		parent::__construct('controllers/modals/editorDecision/form/recommendationForm.tpl');
		$this->_submission = $submission;
		$this->_stageId = $stageId;
		$this->_reviewRound = $reviewRound;

		// Validation checks for this form
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the submission
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Get the stage Id
	 * @return int
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Get the review round object.
	 * @return ReviewRound
	 */
	function getReviewRound() {
		return $this->_reviewRound;
	}

	//
	// Overridden template methods from Form
	//
	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		$submission = $this->getSubmission();

		// Get the decision making editors, the e-mail about the recommendation will be send to
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$userDao = DAORegistry::getDAO('UserDAO');
		$editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), $this->getStageId());
		$editorsStr = '';
		$i = 0;
		foreach ($editorsStageAssignments as $editorsStageAssignment) {
			if (!$editorsStageAssignment->getRecommendOnly()) {
				$editorFullName = $userDao->getUserFullName($editorsStageAssignment->getUserId());
				$editorsStr .= ($i == 0) ? $editorFullName : ', ' . $editorFullName;
				$i++;
			}
		}
		// Get the editor recommendation e-mail template
		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$email = new SubmissionMailTemplate($submission, 'EDITOR_RECOMMENDATION');
		$request = Application::get()->getRequest();
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();
		$user = $request->getUser();
		$submissionUrl = $dispatcher->url($request, ROUTE_PAGE, null, 'workflow', 'index', array($submission->getId(), $this->getStageId()));
		$emailParams = array(
			'editors' => $editorsStr,
			'editorialContactSignature' => $user->getContactSignature(),
			'submissionUrl' => $submissionUrl,
		);
		$email->assignParams($emailParams);
		$email->replaceParams();

		// Get the recorded recommendations
		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
		$editorRecommendations = $editDecisionDao->getEditorDecisions($submission->getId(), $this->getStageId(), null, $user->getId());

		// Set form data
		$recommendationOptions = (new EditorDecisionActionsManager())->getRecommendationOptions($this->getStageId());
		$data = array(
			'submissionId' => $submission->getId(),
			'stageId' => $this->getStageId(),
			'reviewRoundId' => $this->getReviewRound()->getId(),
			'editorRecommendations' => $editorRecommendations,
			'recommendationOptions' => $recommendationOptions,
			'editors' => $editorsStr,
			'personalMessage' => $email->getBody(),
		);
		foreach($data as $key => $value) {
			$this->setData($key, $value);
		}
		return parent::initData();
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('recommendation', 'personalMessage', 'skipEmail', 'skipDiscussion'));
		parent::readInputData();
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute() {
		// Record the recommendation.
		$request = Application::get()->getRequest();
		$submission = $this->getSubmission();
		$reviewRound = $this->getReviewRound();
		$recommendation = $this->getData('recommendation');

		// Record the recommendation
		import('lib.pkp.classes.submission.action.EditorAction');
		$editorAction = new EditorAction();
		// Get editor action labels needed for the recording
		$recommendationOptions = (new EditorDecisionActionsManager())->getRecommendationOptions($this->getStageId());
		$actionLabels = array($recommendation => $recommendationOptions[$recommendation]);
		$editorAction->recordDecision($request, $submission, $recommendation, $actionLabels, $reviewRound, $this->getStageId(), true);

		if (!$this->getData('skipEmail') || !$this->getData('skipDiscussion')) {
			$router = $request->getRouter();
			$user = $request->getUser();

			// Send the email to the decision making editors assigned to this submission.
			import('lib.pkp.classes.mail.SubmissionMailTemplate');
			$email = new SubmissionMailTemplate($submission, 'EDITOR_RECOMMENDATION', null, null, null, false);
			$email->setBody($this->getData('personalMessage'));

			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$userDao = DAORegistry::getDAO('UserDAO');
			$editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), $this->getStageId());
			foreach ($editorsStageAssignments as $editorsStageAssignment) {
				if (!$editorsStageAssignment->getRecommendOnly()) {
					$editor = $userDao->getById($editorsStageAssignment->getUserId());
					$editorFullName = $editor->getFullName();
					$email->addRecipient($editor->getEmail(), $editorFullName);
				}
			}

			DAORegistry::getDAO('SubmissionEmailLogDAO'); // Load constants
			$email->setEventType(SUBMISSION_EMAIL_EDITOR_RECOMMEND_NOTIFY);

			$dispatcher = $router->getDispatcher();
			$submissionUrl = $dispatcher->url($request, ROUTE_PAGE, null, 'workflow', 'index', array($submission->getId(), $this->getStageId()));
			$email->assignParams(array(
				'editors' => $this->getData('editors'),
				'editorialContactSignature' => $user->getContactSignature(),
				'submissionUrl' => $submissionUrl,
				'recommendation' => __($recommendationOptions[$recommendation]),
			));
			if (!$this->getData('skipEmail')) {
				if (!$email->send($request)) {
					import('classes.notification.NotificationManager');
					$notificationMgr = new NotificationManager();
					$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
				}
			}

			if (!$this->getData('skipDiscussion')) {
				// Create a discussion
				$queryDao = DAORegistry::getDAO('QueryDAO');
				$query = $queryDao->newDataObject();
				$query->setAssocType(ASSOC_TYPE_SUBMISSION);
				$query->setAssocId($submission->getId());
				$query->setStageId($this->getStageId());
				$query->setSequence(REALLY_BIG_NUMBER);
				$queryDao->insertObject($query);
				$queryDao->resequence(ASSOC_TYPE_SUBMISSION, $submission->getId());

				// Add the decision making editors as discussion participants
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
				$userDao = DAORegistry::getDAO('UserDAO');
				$discussionParticipantsIds = array();
				$editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), $this->getStageId());
				foreach ($editorsStageAssignments as $editorsStageAssignment) {
					if (!$editorsStageAssignment->getRecommendOnly()) {
						if (!in_array($editorsStageAssignment->getUserId(), $discussionParticipantsIds)) {
							$discussionParticipantsIds[] = $editorsStageAssignment->getUserId();
							$queryDao->insertParticipant($query->getId(), $editorsStageAssignment->getUserId());
						}
					}
				}

				$noteDao = DAORegistry::getDAO('NoteDAO');
				$note = $noteDao->newDataObject();
				$note->setAssocType(ASSOC_TYPE_QUERY);
				$note->setAssocId($query->getId());
				$email->replaceParams();
				$note->setContents($email->getBody());
				$note->setTitle(__('editor.submission.recommendation'));
				$note->setDateCreated(Core::getCurrentDate());
				$note->setDateModified(Core::getCurrentDate());
				$note->setUserId( $user->getId());
				$noteDao->insertObject($note);

				// Add task
				$notificationMgr = new NotificationManager();
				foreach ($discussionParticipantsIds as $discussionParticipantsId) {
					$notificationMgr->createNotification(
						$request,
						$discussionParticipantsId,
						NOTIFICATION_TYPE_NEW_QUERY,
						$request->getContext()->getId(),
						ASSOC_TYPE_QUERY,
						$query->getId(),
						NOTIFICATION_LEVEL_TASK
					);
				}
			}
		}
	}

}


