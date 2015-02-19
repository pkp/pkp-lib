<?php

/**
 * @file classes/submission/EditDecisionDAO.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditDecisionDAO
 * @ingroup submission
 *
 * @brief Operations for retrieving and modifying editor decisions.
 */

// Bring in editor decision constants
// FIXME: These should be standardized into lib-pkp.
import('classes.workflow.EditorDecisionActionsManager');

class EditDecisionDAO extends DAO {
	/**
	 * Constructor.
	 */
	function EditDecisionDAO() {
		parent::DAO();
	}

	/**
	 * Update the editor decision table.
	 * @param $submissionId int
	 * @param $editorDecision array
	 * @param $reviewRound ReviewRound (optional)
	 */
	function updateEditorDecision($submissionId, $editorDecision, $stageId = null, $reviewRound = null) {
		if ($editorDecision['editDecisionId'] == null) {
			$this->update(
				sprintf(
					'INSERT INTO edit_decisions
					(submission_id, review_round_id, stage_id, round, editor_id, decision, date_decided)
					VALUES (?, ?, ?, ?, ?, ?, %s)',
					$this->datetimeToDB($editorDecision['dateDecided'])
				),
				array(
					(int) $submissionId,
					is_a($reviewRound, 'ReviewRound') ? (int) $reviewRound->getId() : 0,
					is_a($reviewRound, 'ReviewRound') ? $reviewRound->getStageId() : (int) $stageId,
					is_a($reviewRound, 'ReviewRound') ? (int) $reviewRound->getRound() : REVIEW_ROUND_NONE,
					(int) $editorDecision['editorId'],
					$editorDecision['decision']
				)
			);
		}
	}

	/**
	 * Delete editing decisions by submission ID.
	 * @param $submissionId int
	 */
	function deleteDecisionsBySubmissionId($submissionId) {
		return $this->update(
			'DELETE FROM edit_decisions WHERE submission_id = ?',
			(int) $submissionId
		);
	}

	/**
	 * Get the editor decisions for a review round of a submission.
	 * @param $submissionId int
	 * @param $stageId int optional
	 * @param $round int optional
	 */
	function getEditorDecisions($submissionId, $stageId = null, $round = null) {
		$params = array((int) $submissionId);
		if ($stageId) $params[] = (int) $stageId;
		if ($round) $params[] = (int) $round;

		$result = $this->retrieve(
			'SELECT	edit_decision_id, editor_id, decision,
				date_decided, review_round_id, stage_id, round
			FROM	edit_decisions
			WHERE	submission_id = ?
				' . ($stageId?' AND stage_id = ?':'') . '
				' . ($round?' AND round = ?':'') . '
			ORDER BY date_decided ASC',
			$params
		);

		$decisions = array();
		while (!$result->EOF) {
			$decisions[] = array(
				'editDecisionId' => $result->fields['edit_decision_id'],
				'reviewRoundId' => $result->fields['review_round_id'],
				'stageId' => $result->fields['stage_id'],
				'round' => $result->fields['round'],
				'editorId' => $result->fields['editor_id'],
				'decision' => $result->fields['decision'],
				'dateDecided' => $this->datetimeFromDB($result->fields['date_decided'])
			);
			$result->MoveNext();
		}
		$result->Close();
		return $decisions;
	}

	/**
	 * Transfer the decisions for an editor.
	 * @param $oldUserId int
	 * @param $newUserId int
	 */
	function transferEditorDecisions($oldUserId, $newUserId) {
		$this->update(
			'UPDATE edit_decisions SET editor_id = ? WHERE editor_id = ?',
			array((int) $newUserId, (int) $oldUserId)
		);
	}
}

?>
