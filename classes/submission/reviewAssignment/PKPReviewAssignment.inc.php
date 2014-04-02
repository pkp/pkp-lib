<?php

/**
 * @file classes/submission/reviewAssignment/PKPReviewAssignment.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewAssignment
 * @ingroup submission
 * @see ReviewAssignmentDAO
 *
 * @brief Describes review assignment properties (abstracted for PKP library).
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

define('REVIEW_ASSIGNMENT_NOT_UNCONSIDERED', 0);
define('REVIEW_ASSIGNMENT_UNCONSIDERED', 1);
define('REVIEW_ASSIGNMENT_UNCONSIDERED_READ', 2);

class PKPReviewAssignment extends DataObject {
	/** @var array The revisions of the reviewer file */
	var $reviewerFileRevisions;

	/**
	 * Constructor.
	 */
	function PKPReviewAssignment() {
		parent::DataObject();
	}

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
		return $this->setData('submissionId', $submissionId);
	}

	/**
	 * Get ID of review assignment.
	 * @return int
	 */
	function getReviewId() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getId();
	}

	/**
	 * Set ID of review assignment
	 * @param $reviewId int
	 */
	function setReviewId($reviewId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->setId($reviewId);
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
		return $this->setData('reviewerId', $reviewerId);
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
		return $this->setData('reviewerFullName', $reviewerFullName);
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
		return $this->setData('comments', $comments);
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
		return $this->setData('competingInterests', $competingInterests);
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
		return $this->setData('stageId', $stageId);
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
		return $this->setData('reviewMethod', $method);
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
	 * Get regret message.
	 * @return string
	 */
	function getRegretMessage() {
		return $this->getData('regretMessage');
	}

	/**
	 * Set regret message.
	 * @param $regretMessage string
	 */
	function setRegretMessage($regretMessage) {
		return $this->setData('regretMessage', $regretMessage);
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
		return $this->setData('recommendation', $recommendation);
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
		return $this->setData('unconsidered', $unconsidered);
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
		return $this->setData('dateRated', $dateRated);
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
		return $this->setData('lastModified', $dateModified);
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
		return $this->setData('dateAssigned', $dateAssigned);
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
		return $this->setData('dateNotified', $dateNotified);
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
		return $this->setData('dateConfirmed', $dateConfirmed);
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
		return $this->setData('dateCompleted', $dateCompleted);
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
		return $this->setData('dateAcknowledged', $dateAcknowledged);
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
		return $this->setData('dateReminded', $dateReminded);
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
		return $this->setData('dateDue', $dateDue);
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
		return $this->setData('dateResponseDue', $dateResponseDue);
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
		return $this->setData('declined', $declined);
	}

	/**
	 * Get the replaced value.
	 * @return boolean
	 */
	function getReplaced() {
		return $this->getData('replaced');
	}

	/**
	 * Set the reviewer's replaced value.
	 * @param $replaced boolean
	 */
	function setReplaced($replaced) {
		return $this->setData('replaced', $replaced);
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
		return $this->setData('reminderWasAutomatic', $wasAutomatic);
	}

	/**
	 * Get the cancelled value.
	 * @return boolean
	 */
	function getCancelled() {
		return $this->getData('cancelled');
	}

	/**
	 * Set the reviewer's cancelled value.
	 * @param $cancelled boolean
	 */
	function setCancelled($cancelled) {
		return $this->setData('cancelled', $cancelled);
	}

	/**
	 * Get reviewer file id.
	 * @return int
	 */
	function getReviewerFileId() {
		return $this->getData('reviewerFileId');
	}

	/**
	 * Set reviewer file id.
	 * @param $reviewerFileId int
	 */
	function setReviewerFileId($reviewerFileId) {
		return $this->setData('reviewerFileId', $reviewerFileId);
	}

	/**
	 * Get reviewer file viewable.
	 * @return boolean
	 */
	function getReviewerFileViewable() {
		return $this->getData('reviewerFileViewable');
	}

	/**
	 * Set reviewer file viewable.
	 * @param $reviewerFileViewable boolean
	 */
	function setReviewerFileViewable($reviewerFileViewable) {
		return $this->setData('reviewerFileViewable', $reviewerFileViewable);
	}

	/**
	 * Get quality.
	 * @return int
	 */
	function getQuality() {
		return $this->getData('quality');
	}

	/**
	 * Set quality.
	 * @param $quality int
	 */
	function setQuality($quality) {
		return $this->setData('quality', $quality);
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
		return $this->setData('round', $round);
	}

	/**
	 * Get review file id.
	 * @return int
	 */
	function getReviewFileId() {
		return $this->getData('reviewFileId');
	}

	/**
	 * Set review file id.
	 * @param $reviewFileId int
	 */
	function setReviewFileId($reviewFileId) {
		return $this->setData('reviewFileId', $reviewFileId);
	}

	/**
	 * Get review file.
	 * @return object
	 */
	function &getReviewFile() {
		$returner =& $this->getData('reviewFile');
		return $returner;
	}

	/**
	 * Set review file.
	 * @param $reviewFile object
	 */
	function setReviewFile($reviewFile) {
		return $this->setData('reviewFile', $reviewFile);
	}

	/**
	 * Get review revision.
	 * @return int
	 */
	function getReviewRevision() {
		return $this->getData('reviewRevision');
	}

	/**
	 * Set review revision.
	 * @param $reviewRevision int
	 */
	function setReviewRevision($reviewRevision) {
		return $this->setData('reviewRevision', $reviewRevision);
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
		return $this->setData('reviewFormId', $reviewFormId);
	}

	//
	// Files
	//

	/**
	 * Get reviewer file.
	 * @return ArticleFile
	 */
	function &getReviewerFile() {
		$returner =& $this->getData('reviewerFile');
		return $returner;
	}

	/**
	 * Set reviewer file.
	 * @param $reviewFile ArticleFile
	 */
	function setReviewerFile($reviewerFile) {
		return $this->setData('reviewerFile', $reviewerFile);
	}

	/**
	 * Get all reviewer file revisions.
	 * @return array ArticleFiles
	 */
	function getReviewerFileRevisions() {
		return $this->reviewerFileRevisions;
	}

	/**
	 * Set all reviewer file revisions.
	 * @param $reviewerFileRevisions array ArticleFiles
	 */
	function setReviewerFileRevisions($reviewerFileRevisions) {
		return $this->reviewerFileRevisions = $reviewerFileRevisions;
	}

	/**
	 * Get supplementary files for this article.
	 * @return array SuppFiles
	 */
	function &getSuppFiles() {
		$returner =& $this->getData('suppFiles');
		return $returner;
	}

	/**
	 * Set supplementary file for this article.
	 * @param $suppFiles array SuppFiles
	 */
	function setSuppFiles($suppFiles) {
		return $this->setData('suppFiles', $suppFiles);
	}

	/**
	 * Get number of weeks until review is due (or number of weeks overdue).
	 * @return int
	 */
	function getWeeksDue() {
		$dateDue = $this->getDateDue();
		if ($dateDue === null) return null;
		return round((strtotime($dateDue) - time()) / (86400 * 7.0));
	}

	//
	// Comments
	//

	/**
	 * Get most recent peer review comment.
	 * @return ArticleComment
	 */
	function getMostRecentPeerReviewComment() {
		return $this->getData('peerReviewComment');
	}

	/**
	 * Set most recent peer review comment.
	 * @param $peerReviewComment ArticleComment
	 */
	function setMostRecentPeerReviewComment($peerReviewComment) {
		return $this->setData('peerReviewComment', $peerReviewComment);
	}
}

?>
