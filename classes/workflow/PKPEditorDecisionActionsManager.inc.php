<?php

/**
 * @file classes/workflow/PKPEditorDecisionActionsManager.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPEditorDecisionActionsManager
 * @ingroup classes_workflow
 *
 * @brief Wrapper class for create and assign editor decisions actions to template manager.
 */

define('SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE', 9);

define('SUBMISSION_EDITOR_RECOMMEND_ACCEPT', 11);
define('SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS', 12);
define('SUBMISSION_EDITOR_RECOMMEND_RESUBMIT', 13);
define('SUBMISSION_EDITOR_RECOMMEND_DECLINE', 14);

abstract class PKPEditorDecisionActionsManager {
	/**
	 * Get the available decisions by stage ID and user making decision permissions,
	 * if the user can make decisions or if it is recommendOnly user.
	 * @param $context Context
	 * @param $stageId int WORKFLOW_STAGE_ID_...
	 * @param $makeDecision boolean If the user can make decisions
	 */
	public function getStageDecisions($context, $stageId, $makeDecision = true) {
		$result = null;
		switch ($stageId) {
			case WORKFLOW_STAGE_ID_SUBMISSION:
				$result = $this->_submissionStageDecisions($stageId, $makeDecision);
				break;
			case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
				$result = $this->_externalReviewStageDecisions($context, $makeDecision);
				break;
			case WORKFLOW_STAGE_ID_EDITING:
				$result = $this->_editorialStageDecisions($makeDecision);
				break;
			default:
				assert(false);
		}
		HookRegistry::call('EditorAction::modifyDecisionOptions',
			array($context, $stageId, &$makeDecision, &$result));
		return $result;
	}

	/**
	 * Get an associative array matching editor recommendation codes with locale strings.
	 * (Includes default '' => "Choose One" string.)
	 * @param $stageId integer
	 * @return array recommendation => localeString
	 */
	public function getRecommendationOptions($stageId) {
		return array(
			'' => 'common.chooseOne',
			SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS => 'editor.submission.decision.requestRevisions',
			SUBMISSION_EDITOR_RECOMMEND_RESUBMIT => 'editor.submission.decision.resubmit',
			SUBMISSION_EDITOR_RECOMMEND_ACCEPT => 'editor.submission.decision.accept',
			SUBMISSION_EDITOR_RECOMMEND_DECLINE => 'editor.submission.decision.decline',
		);
	}

	/**
	 * Define and return editor decisions for the submission stage.
	 * If the user cannot make decisions i.e. if it is a recommendOnly user,
	 * the user can only send the submission to the review stage, and neither
	 * acept nor decline the submission.
	 * @param $stageId int WORKFLOW_STAGE_ID_...
	 * @param $makeDecision boolean If the user can make decisions
	 * @return array
	 */
	protected function _submissionStageDecisions($stageId, $makeDecision = true) {
		$decisions = array(
			SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW => array(
				'operation' => 'externalReview',
				'name' => 'externalReview',
				'title' => 'editor.submission.decision.sendExternalReview',
				'toStage' => 'editor.review',
			)
		);
		if ($makeDecision) {
			if ($stageId == WORKFLOW_STAGE_ID_SUBMISSION) {
				$decisions = $decisions + array(
					SUBMISSION_EDITOR_DECISION_ACCEPT => array(
						'name' => 'accept',
						'operation' => 'promote',
						'title' => 'editor.submission.decision.skipReview',
						'toStage' => 'submission.copyediting',
					),
				);
			}

			$decisions = $decisions + array(
				SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE => array(
					'name' => 'decline',
					'operation' => 'sendReviews',
					'title' => 'editor.submission.decision.decline',
				),
			);
		}
		return $decisions;
	}

	/**
	 * Define and return editor decisions for the editorial stage.
	 * Currently it does not matter if the user cannot make decisions
	 * i.e. if it is a recommendOnly user for this stage.
	 * @param $makeDecision boolean If the user cannot make decisions
	 * @return array
	 */
	protected function _editorialStageDecisions($makeDecision = true) {
		return array(
			SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION => array(
				'operation' => 'promote',
				'name' => 'sendToProduction',
				'title' => 'editor.submission.decision.sendToProduction',
				'toStage' => 'submission.production',
			),
		);
	}

	/**
	 * Get the stage-level notification type constants.
	 * @return array
	 */
	public function getStageNotifications() {
		return array(
			NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION,
			NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW,
			NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING,
			NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION
		);
	}
}

