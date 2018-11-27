<?php

/**
 * @file classes/submission/reviewAssignment/ReviewAssignment.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignment
 * @ingroup submission
 * @see ReviewAssignmentDAO
 *
 * @brief Describes review assignment properties.
 */

define('SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT', 1);
define('SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS', 2);
define('SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE', 3);
define('SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE', 4);
define('SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE', 5);
define('SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS', 6);

define('SUBMISSION_REVIEWER_RATING_VERY_GOOD', 5);
define('SUBMISSION_REVIEWER_RATING_GOOD', 4);
define('SUBMISSION_REVIEWER_RATING_AVERAGE', 3);
define('SUBMISSION_REVIEWER_RATING_POOR', 2);
define('SUBMISSION_REVIEWER_RATING_VERY_POOR', 1);

define('SUBMISSION_REVIEW_METHOD_BLIND', 1);
define('SUBMISSION_REVIEW_METHOD_DOUBLEBLIND', 2);
define('SUBMISSION_REVIEW_METHOD_OPEN', 3);

// A review is "unconsidered" when it is confirmed by an editor and then that
// confirmation is later revoked.
define('REVIEW_ASSIGNMENT_NOT_UNCONSIDERED', 0); // Has never been unconsidered
define('REVIEW_ASSIGNMENT_UNCONSIDERED', 1); // Has been unconsindered and is awaiting re-confirmation by an editor
define('REVIEW_ASSIGNMENT_UNCONSIDERED_READ', 2); // Has been reconfirmed by an editor

define('REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE', 0); // request has been sent but reviewer has not responded
define('REVIEW_ASSIGNMENT_STATUS_DECLINED', 1); // reviewer declind review request
define('REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE', 4); // review not responded within due date
define('REVIEW_ASSIGNMENT_STATUS_ACCEPTED', 5); // reviewer has agreed to the review
define('REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE', 6); // review not submitted within due date
define('REVIEW_ASSIGNMENT_STATUS_RECEIVED', 7); // review has been submitted
define('REVIEW_ASSIGNMENT_STATUS_COMPLETE', 8); // review has been confirmed by an editor
define('REVIEW_ASSIGNMENT_STATUS_THANKED', 9); // reviewer has been thanked

class ReviewAssignment extends DataObject {

	//
	// Get/set methods
	//

	/**
	 * Get ID of review assignment's submission.
	 * @return int
	 */
	function getSubmissionId() {
		return $this->getData('submissionId');
	}

	/**
	 * Set ID of review assignment's submission
	 * @param $submissionId int
	 */
	function setSubmissionId($submissionId) {
		$this->setData('submissionId', $submissionId);
	}

	/**
	 * Get ID of reviewer.
	 * @return int
	 */
	function getReviewerId() {
		return $this->getData('reviewerId');
	}

	/**
	 * Set ID of reviewer.
	 * @param $reviewerId int
	 */
	function setReviewerId($reviewerId) {
		$this->setData('reviewerId', $reviewerId);
	}

	/**
	 * Get full name of reviewer.
	 * @return string
	 */
	function getReviewerFullName() {
		return $this->getData('reviewerFullName');
	}

	/**
	 * Set full name of reviewer.
	 * @param $reviewerFullName string
	 */
	function setReviewerFullName($reviewerFullName) {
		$this->setData('reviewerFullName', $reviewerFullName);
	}

	/**
	 * Get reviewer comments.
	 * @return string
	 */
	function getComments() {
		return $this->getData('comments');
	}

	/**
	 * Set reviewer comments.
	 * @param $comments string
	 */
	function setComments($comments) {
		$this->setData('comments', $comments);
	}

	/**
	 * Get competing interests.
	 * @return string
	 */
	function getCompetingInterests() {
		return $this->getData('competingInterests');
	}

	/**
	 * Set competing interests.
	 * @param $competingInterests string
	 */
	function setCompetingInterests($competingInterests) {
		$this->setData('competingInterests', $competingInterests);
	}

	/**
	 * Get the workflow stage id.
	 * @return int
	 */
	function getStageId() {
		return $this->getData('stageId');
	}

	/**
	 * Set the workflow stage id.
	 * @param $stageId int
	 */
	function setStageId($stageId) {
		$this->setData('stageId', $stageId);
	}

	/**
	 * Get the method of the review (open, blind, or double-blind).
	 * @return int
	 */
	function getReviewMethod() {
		return $this->getData('reviewMethod');
	}

	/**
	 * Set the type of review.
	 * @param $method int
	 */
	function setReviewMethod($method) {
		$this->setData('reviewMethod', $method);
	}

	/**
	 * Get review round id.
	 * @return int
	 */
	function getReviewRoundId() {
		return $this->getData('reviewRoundId');
	}

	/**
	 * Set review round id.
	 * @param $reviewRoundId int
	 */
	function setReviewRoundId($reviewRoundId) {
		$this->setData('reviewRoundId', $reviewRoundId);
	}

	/**
	 * Get reviewer recommendation.
	 * @return string
	 */
	function getRecommendation() {
		return $this->getData('recommendation');
	}

	/**
	 * Set reviewer recommendation.
	 * @param $recommendation string
	 */
	function setRecommendation($recommendation) {
		$this->setData('recommendation', $recommendation);
	}

	/**
	 * Get unconsidered state.
	 * @return int
	 */
	function getUnconsidered() {
		return $this->getData('unconsidered');
	}

	/**
	 * Set unconsidered state.
	 * @param $unconsidered int
	 */
	function setUnconsidered($unconsidered) {
		$this->setData('unconsidered', $unconsidered);
	}

	/**
	 * Get the date the reviewer was rated.
	 * @return string
	 */
	function getDateRated() {
		return $this->getData('dateRated');
	}

	/**
	 * Set the date the reviewer was rated.
	 * @param $dateRated string
	 */
	function setDateRated($dateRated) {
		$this->setData('dateRated', $dateRated);
	}

	/**
	 * Get the date of the last modification.
	 * @return date
	 */
	function getLastModified() {
		return $this->getData('lastModified');
	}

	/**
	 * Set the date of the last modification.
	 * @param $dateModified date
	 */
	function setLastModified($dateModified) {
		$this->setData('lastModified', $dateModified);
	}

	/**
	 * Stamp the date of the last modification to the current time.
	 */
	function stampModified() {
		return $this->setLastModified(Core::getCurrentDate());
	}

	/**
	 * Get the reviewer's assigned date.
	 * @return string
	 */
	function getDateAssigned() {
		return $this->getData('dateAssigned');
	}

	/**
	 * Set the reviewer's assigned date.
	 * @param $dateAssigned string
	 */
	function setDateAssigned($dateAssigned) {
		$this->setData('dateAssigned', $dateAssigned);
	}

	/**
	 * Get the reviewer's notified date.
	 * @return string
	 */
	function getDateNotified() {
		return $this->getData('dateNotified');
	}

	/**
	 * Set the reviewer's notified date.
	 * @param $dateNotified string
	 */
	function setDateNotified($dateNotified) {
		$this->setData('dateNotified', $dateNotified);
	}

	/**
	 * Get the reviewer's confirmed date.
	 * @return string
	 */
	function getDateConfirmed() {
		return $this->getData('dateConfirmed');
	}

	/**
	 * Set the reviewer's confirmed date.
	 * @param $dateConfirmed string
	 */
	function setDateConfirmed($dateConfirmed) {
		$this->setData('dateConfirmed', $dateConfirmed);
	}

	/**
	 * Get the reviewer's completed date.
	 * @return string
	 */
	function getDateCompleted() {
		return $this->getData('dateCompleted');
	}

	/**
	 * Set the reviewer's completed date.
	 * @param $dateCompleted string
	 */
	function setDateCompleted($dateCompleted) {
		$this->setData('dateCompleted', $dateCompleted);
	}

	/**
	 * Get the reviewer's acknowledged date.
	 * @return string
	 */
	function getDateAcknowledged() {
		return $this->getData('dateAcknowledged');
	}

	/**
	 * Set the reviewer's acknowledged date.
	 * @param $dateAcknowledged string
	 */
	function setDateAcknowledged($dateAcknowledged) {
		$this->setData('dateAcknowledged', $dateAcknowledged);
	}

	/**
	 * Get the reviewer's last reminder date.
	 * @return string
	 */
	function getDateReminded() {
		return $this->getData('dateReminded');
	}

	/**
	 * Set the reviewer's last reminder date.
	 * @param $dateReminded string
	 */
	function setDateReminded($dateReminded) {
		$this->setData('dateReminded', $dateReminded);
	}

	/**
	 * Get the reviewer's due date.
	 * @return string
	 */
	function getDateDue() {
		return $this->getData('dateDue');
	}

	/**
	 * Set the reviewer's due date.
	 * @param $dateDue string
	 */
	function setDateDue($dateDue) {
		$this->setData('dateDue', $dateDue);
	}

	/**
	 * Get the reviewer's response due date.
	 * @return string
	 */
	function getDateResponseDue() {
		return $this->getData('dateResponseDue');
	}

	/**
	 * Set the reviewer's response due date.
	 * @param $dateResponseDue string
	 */
	function setDateResponseDue($dateResponseDue) {
		$this->setData('dateResponseDue', $dateResponseDue);
	}

	/**
	 * Get the declined value.
	 * @return boolean
	 */
	function getDeclined() {
		return $this->getData('declined');
	}

	/**
	 * Set the reviewer's declined value.
	 * @param $declined boolean
	 */
	function setDeclined($declined) {
		$this->setData('declined', $declined);
	}

	/**
	 * Get a boolean indicating whether or not the last reminder was automatic.
	 * @return boolean
	 */
	function getReminderWasAutomatic() {
		return $this->getData('reminderWasAutomatic')==1?1:0;
	}

	/**
	 * Set the boolean indicating whether or not the last reminder was automatic.
	 * @param $wasAutomatic boolean
	 */
	function setReminderWasAutomatic($wasAutomatic) {
		$this->setData('reminderWasAutomatic', $wasAutomatic);
	}

	/**
	 * Get quality.
	 * @return int|null
	 */
	function getQuality() {
		return $this->getData('quality');
	}

	/**
	 * Set quality.
	 * @param $quality int|null
	 */
	function setQuality($quality) {
		$this->setData('quality', $quality);
	}

	/**
	 * Get round.
	 * @return int
	 */
	function getRound() {
		return $this->getData('round');
	}

	/**
	 * Set round.
	 * @param $round int
	 */
	function setRound($round) {
		$this->setData('round', $round);
	}

	/**
	 * Get review form id.
	 * @return int
	 */
	function getReviewFormId() {
		return $this->getData('reviewFormId');
	}

	/**
	 * Set review form id.
	 * @param $reviewFormId int
	 */
	function setReviewFormId($reviewFormId) {
		$this->setData('reviewFormId', $reviewFormId);
	}

	/**
	 * Get the current status of this review assignment
	 *
	 * @return int
	 */
	function getStatus() {

		if ($this->getDeclined()) {
			return REVIEW_ASSIGNMENT_STATUS_DECLINED;
		} elseif (!$this->getDateCompleted()) {
			$dueTimes = array_map(function($dateTime) {
					// If no due time, set it to the end of the day
					if (substr($dateTime, 11) === '00:00:00') {
						$dateTime = substr($dateTime, 0, 11) . '23:59:59';
					}
					return strtotime($dateTime);
				}, array($this->getDateResponseDue(), $this->getDateDue()));
			$responseDueTime = $dueTimes[0];
			$reviewDueTime = $dueTimes[1];
			if (!$this->getDateConfirmed()){ // no response
				if($responseDueTime < time()) { // response overdue
					return REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE;
				} elseif ($reviewDueTime < strtotime('tomorrow')) { // review overdue but not response
					return REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE;
				} else { // response not due yet
					return REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE;
				}
			} else { // response given
				if ($reviewDueTime < strtotime('tomorrow')) { // review due
					return REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE;
				} else {
					return REVIEW_ASSIGNMENT_STATUS_ACCEPTED;
				}
			}
		} elseif ($this->getDateAcknowledged()) { // reviewer thanked...
			if ($this->getUnconsidered() == REVIEW_ASSIGNMENT_UNCONSIDERED) { // ...but review later unconsidered
				return REVIEW_ASSIGNMENT_STATUS_RECEIVED;
			}
			return REVIEW_ASSIGNMENT_STATUS_THANKED;
		} elseif ($this->getDateCompleted()) { // review submitted...
			if ($this->getUnconsidered() != REVIEW_ASSIGNMENT_UNCONSIDERED && $this->isRead()) { // ...and confirmed by an editor
				return REVIEW_ASSIGNMENT_STATUS_COMPLETE;
			}
			return REVIEW_ASSIGNMENT_STATUS_RECEIVED;
		}

		return REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE;
	}

	/**
	 * Determine whether an editorial user has read this review
	 *
	 * @return bool
	 */
	function isRead() {
		$submissionDao = Application::getSubmissionDAO();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO');
		$viewsDao = DAORegistry::getDAO('ViewsDAO');

		$submission = $submissionDao->getById($this->getSubmissionId());

		// Get the user groups for this stage
		$userGroups = $userGroupDao->getUserGroupsByStage(
			$submission->getContextId(),
			$this->getStageId()
		);
		while ($userGroup = $userGroups->next()) {
			$roleId = $userGroup->getRoleId();
			if ($roleId != ROLE_ID_MANAGER && $roleId != ROLE_ID_SUB_EDITOR) {
				continue;
			}

			// Get the users assigned to this stage and user group
			$stageUsers = $userStageAssignmentDao->getUsersBySubmissionAndStageId(
				$this->getSubmissionId(),
				$this->getStageId(),
				$userGroup->getId()
			);

			// Check if any of these users have viewed it
			while ($user = $stageUsers->next()) {
				if ($viewsDao->getLastViewDate(
					ASSOC_TYPE_REVIEW_RESPONSE,
					$this->getId(),
					$user->getId()
				)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the translation key for the current status
	 *
	 * @param int $status Optionally pass a status to retrieve a specific key.
	 *  Default will return the key for the current status.
	 * @return string
	 */
	public function getStatusKey($status = null) {

		if (is_null($status)) {
			$status = $this->getStatus();
		}

		switch ($status) {
			case REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE:
				return 'submission.review.status.awaitingResponse';
			case REVIEW_ASSIGNMENT_STATUS_DECLINED:
				return 'submission.review.status.declined';
			case REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE:
				return 'submission.review.status.responseOverdue';
			case REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE:
				return 'submission.review.status.reviewOverdue';
			case REVIEW_ASSIGNMENT_STATUS_ACCEPTED:
				return 'submission.review.status.accepted';
			case REVIEW_ASSIGNMENT_STATUS_RECEIVED:
				return 'submission.review.status.received';
			case REVIEW_ASSIGNMENT_STATUS_COMPLETE:
				return 'submission.review.status.complete';
			case REVIEW_ASSIGNMENT_STATUS_THANKED:
				return 'submission.review.status.thanked';
		}

		assert(false, 'No status key could be found for ' . get_class($this) . ' on ' . __LINE__);

		return '';
	}

	/**
	 * Get the translation key for the review method
	 *
	 * @param $method int|null Optionally pass a method to retrieve a specific key.
	 *  Default will return the key for the current review method
	 * @return string
	 */
	public function getReviewMethodKey($method = null) {

		if (is_null($method)) {
			$method = $this->getReviewMethod();
		}

		switch ($method) {
			case SUBMISSION_REVIEW_METHOD_OPEN:
				return 'editor.submissionReview.open';
			case SUBMISSION_REVIEW_METHOD_BLIND:
				return 'editor.submissionReview.blind';
			case SUBMISSION_REVIEW_METHOD_DOUBLEBLIND:
				return 'editor.submissionReview.doubleBlind';
		}

		assert(false, 'No review method key could be found for ' . get_class($this) . ' on ' . __LINE__);

		return '';
	}

	//
	// Files
	//

	/**
	 * Get number of weeks until review is due (or number of weeks overdue).
	 * @return int
	 */
	function getWeeksDue() {
		$dateDue = $this->getDateDue();
		if ($dateDue === null) return null;
		return round((strtotime($dateDue) - time()) / (86400 * 7.0));
	}

	/**
	 * Get an associative array matching reviewer recommendation codes with locale strings.
	 * (Includes default '' => "Choose One" string.)
	 * @return array recommendation => localeString
	 */
	function getReviewerRecommendationOptions() {

		static $reviewerRecommendationOptions = array(
				'' => 'common.chooseOne',
				SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT => 'reviewer.article.decision.accept',
				SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS => 'reviewer.article.decision.pendingRevisions',
				SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE => 'reviewer.article.decision.resubmitHere',
				SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE => 'reviewer.article.decision.resubmitElsewhere',
				SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE => 'reviewer.article.decision.decline',
				SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS => 'reviewer.article.decision.seeComments'
		);
		return $reviewerRecommendationOptions;
	}

	/**
	 * Return a localized string representing the reviewer recommendation.
	 */
	function getLocalizedRecommendation() {

		$options = self::getReviewerRecommendationOptions();
		if (array_key_exists($this->getRecommendation(), $options)) {
			return __($options[$this->getRecommendation()]);
		} else {
			return '';
		}
	}
}
