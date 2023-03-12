<?php

/**
 * @file classes/submission/reviewAssignment/ReviewAssignment.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignment
 * @ingroup submission
 *
 * @see ReviewAssignmentDAO
 *
 * @brief Describes review assignment properties.
 */

namespace PKP\submission\reviewAssignment;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\security\Role;

class ReviewAssignment extends \PKP\core\DataObject
{
    public const SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT = 1;
    public const SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS = 2;
    public const SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE = 3;
    public const SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE = 4;
    public const SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE = 5;
    public const SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS = 6;

    public const SUBMISSION_REVIEWER_RATING_VERY_GOOD = 5;
    public const SUBMISSION_REVIEWER_RATING_GOOD = 4;
    public const SUBMISSION_REVIEWER_RATING_AVERAGE = 3;
    public const SUBMISSION_REVIEWER_RATING_POOR = 2;
    public const SUBMISSION_REVIEWER_RATING_VERY_POOR = 1;

    public const SUBMISSION_REVIEW_METHOD_ANONYMOUS = 1;
    public const SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS = 2;
    public const SUBMISSION_REVIEW_METHOD_OPEN = 3;

    public const REVIEW_ASSIGNMENT_NEW = 0; // Has never been considered by an editor, review assignment just created
    public const REVIEW_ASSIGNMENT_CONSIDERED = 3; // Has been marked considered by an editor
    public const REVIEW_ASSIGNMENT_UNCONSIDERED = 1; // Considered status has been revoked by an editor and is awaiting re-confirmation by an editor
    public const REVIEW_ASSIGNMENT_RECONSIDERED = 2; // Considered status has been granted again by an editor

    public const REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE = 0; // request has been sent but reviewer has not responded
    public const REVIEW_ASSIGNMENT_STATUS_DECLINED = 1; // reviewer declined review request
    public const REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE = 4; // review not responded within due date
    public const REVIEW_ASSIGNMENT_STATUS_ACCEPTED = 5; // reviewer has agreed to the review
    public const REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE = 6; // review not submitted within due date
    public const REVIEW_ASSIGNMENT_STATUS_RECEIVED = 7; // review has been submitted
    public const REVIEW_ASSIGNMENT_STATUS_COMPLETE = 8; // review has been confirmed by an editor
    public const REVIEW_ASSIGNMENT_STATUS_THANKED = 9; // reviewer has been thanked
    public const REVIEW_ASSIGNMENT_STATUS_CANCELLED = 10; // reviewer cancelled review request
    public const REVIEW_ASSIGNMENT_STATUS_REQUEST_RESEND = 11; // request resent to reviewer after they declined

    /**
     * All review assignment statuses that indicate a
     * review was completed
     *
     * @var array<int>
     */
    public const REVIEW_COMPLETE_STATUSES = [
        self::REVIEW_ASSIGNMENT_STATUS_RECEIVED,
        self::REVIEW_ASSIGNMENT_STATUS_COMPLETE,
        self::REVIEW_ASSIGNMENT_STATUS_THANKED,
    ];

    //
    // Get/set methods
    //

    /**
     * Get ID of review assignment's submission.
     *
     * @return int
     */
    public function getSubmissionId()
    {
        return $this->getData('submissionId');
    }

    /**
     * Set ID of review assignment's submission
     *
     * @param int $submissionId
     */
    public function setSubmissionId($submissionId)
    {
        $this->setData('submissionId', $submissionId);
    }

    /**
     * Get ID of reviewer.
     *
     * @return int
     */
    public function getReviewerId()
    {
        return $this->getData('reviewerId');
    }

    /**
     * Set ID of reviewer.
     *
     * @param int $reviewerId
     */
    public function setReviewerId($reviewerId)
    {
        $this->setData('reviewerId', $reviewerId);
    }

    /**
     * Get full name of reviewer.
     *
     * @return string
     */
    public function getReviewerFullName()
    {
        return $this->getData('reviewerFullName');
    }

    /**
     * Set full name of reviewer.
     *
     * @param string $reviewerFullName
     */
    public function setReviewerFullName($reviewerFullName)
    {
        $this->setData('reviewerFullName', $reviewerFullName);
    }

    /**
     * Get reviewer comments.
     *
     * @return string
     */
    public function getComments()
    {
        return $this->getData('comments');
    }

    /**
     * Set reviewer comments.
     *
     * @param string $comments
     */
    public function setComments($comments)
    {
        $this->setData('comments', $comments);
    }

    /**
     * Get competing interests.
     *
     * @return string
     */
    public function getCompetingInterests()
    {
        return $this->getData('competingInterests');
    }

    /**
     * Set competing interests.
     *
     * @param string $competingInterests
     */
    public function setCompetingInterests($competingInterests)
    {
        $this->setData('competingInterests', $competingInterests);
    }

    /**
     * Get the workflow stage id.
     *
     * @return int WORKFLOW_STAGE_ID_...
     */
    public function getStageId()
    {
        return $this->getData('stageId');
    }

    /**
     * Set the workflow stage id.
     *
     * @param int $stageId WORKFLOW_STAGE_ID_...
     */
    public function setStageId($stageId)
    {
        $this->setData('stageId', $stageId);
    }

    /**
     * Get the method of the review (open, anonymous, or double-anonymous).
     *
     * @return int
     */
    public function getReviewMethod()
    {
        return $this->getData('reviewMethod');
    }

    /**
     * Set the type of review.
     *
     * @param int $method
     */
    public function setReviewMethod($method)
    {
        $this->setData('reviewMethod', $method);
    }

    /**
     * Get review round id.
     *
     * @return int
     */
    public function getReviewRoundId()
    {
        return $this->getData('reviewRoundId');
    }

    /**
     * Set review round id.
     *
     * @param int $reviewRoundId
     */
    public function setReviewRoundId($reviewRoundId)
    {
        $this->setData('reviewRoundId', $reviewRoundId);
    }

    /**
     * Get reviewer recommendation.
     *
     * @return string
     */
    public function getRecommendation()
    {
        return $this->getData('recommendation');
    }

    /**
     * Set reviewer recommendation.
     *
     * @param string $recommendation
     */
    public function setRecommendation($recommendation)
    {
        $this->setData('recommendation', $recommendation);
    }

    /**
     * Get considered state.
     *
     * @return int
     */
    public function getConsidered()
    {
        return $this->getData('considered');
    }

    /**
     * Set considered state.
     *
     * @param int $considered
     */
    public function setConsidered($considered)
    {
        $this->setData('considered', $considered);
    }

    /**
     * Get the date the reviewer was rated.
     *
     * @return string
     */
    public function getDateRated()
    {
        return $this->getData('dateRated');
    }

    /**
     * Set the date the reviewer was rated.
     *
     * @param string $dateRated
     */
    public function setDateRated($dateRated)
    {
        $this->setData('dateRated', $dateRated);
    }

    /**
     * Get the date of the last modification.
     *
     * @return string
     */
    public function getLastModified()
    {
        return $this->getData('lastModified');
    }

    /**
     * Set the date of the last modification.
     *
     * @param string $dateModified
     */
    public function setLastModified($dateModified)
    {
        $this->setData('lastModified', $dateModified);
    }

    /**
     * Stamp the date of the last modification to the current time.
     */
    public function stampModified()
    {
        return $this->setLastModified(Core::getCurrentDate());
    }

    /**
     * Get the reviewer's assigned date.
     *
     * @return string
     */
    public function getDateAssigned()
    {
        return $this->getData('dateAssigned');
    }

    /**
     * Set the reviewer's assigned date.
     *
     * @param string $dateAssigned
     */
    public function setDateAssigned($dateAssigned)
    {
        $this->setData('dateAssigned', $dateAssigned);
    }

    /**
     * Get the reviewer's notified date.
     *
     * @return string
     */
    public function getDateNotified()
    {
        return $this->getData('dateNotified');
    }

    /**
     * Set the reviewer's notified date.
     *
     * @param string $dateNotified
     */
    public function setDateNotified($dateNotified)
    {
        $this->setData('dateNotified', $dateNotified);
    }

    /**
     * Get the reviewer's confirmed date.
     *
     * @return string|null
     */
    public function getDateConfirmed()
    {
        return $this->getData('dateConfirmed');
    }

    /**
     * Set the reviewer's confirmed date.
     *
     * @param string|null $dateConfirmed
     */
    public function setDateConfirmed($dateConfirmed)
    {
        $this->setData('dateConfirmed', $dateConfirmed);
    }

    /**
     * Get the reviewer's completed date.
     *
     * @return string
     */
    public function getDateCompleted()
    {
        return $this->getData('dateCompleted');
    }

    /**
     * Set the reviewer's completed date.
     *
     * @param string $dateCompleted
     */
    public function setDateCompleted($dateCompleted)
    {
        $this->setData('dateCompleted', $dateCompleted);
    }

    /**
     * Get the reviewer's acknowledged date.
     *
     * @return string
     */
    public function getDateAcknowledged()
    {
        return $this->getData('dateAcknowledged');
    }

    /**
     * Set the reviewer's acknowledged date.
     *
     * @param string $dateAcknowledged
     */
    public function setDateAcknowledged($dateAcknowledged)
    {
        $this->setData('dateAcknowledged', $dateAcknowledged);
    }

    /**
     * Get the reviewer's last reminder date.
     *
     * @return string
     */
    public function getDateReminded()
    {
        return $this->getData('dateReminded');
    }

    /**
     * Set the reviewer's last reminder date.
     *
     * @param string $dateReminded
     */
    public function setDateReminded($dateReminded)
    {
        $this->setData('dateReminded', $dateReminded);
    }

    /**
     * Get the reviewer's due date.
     *
     * @return string
     */
    public function getDateDue()
    {
        return $this->getData('dateDue');
    }

    /**
     * Set the reviewer's due date.
     *
     * @param string $dateDue
     */
    public function setDateDue($dateDue)
    {
        $this->setData('dateDue', $dateDue);
    }

    /**
     * Get the reviewer's response due date.
     *
     * @return string
     */
    public function getDateResponseDue()
    {
        return $this->getData('dateResponseDue');
    }

    /**
     * Set the reviewer's response due date.
     *
     * @param string $dateResponseDue
     */
    public function setDateResponseDue($dateResponseDue)
    {
        $this->setData('dateResponseDue', $dateResponseDue);
    }

    /**
     * Get the declined value.
     *
     * @return bool
     */
    public function getDeclined()
    {
        return $this->getData('declined');
    }

    /**
     * Set the reviewer's declined value.
     *
     * @param bool $declined
     */
    public function setDeclined($declined)
    {
        $this->setData('declined', $declined);
    }

    /**
     * Get the cancelled value.
     *
     * @return bool
     */
    public function getCancelled()
    {
        return $this->getData('cancelled');
    }

    /**
     * Set the reviewer's cancelled value.
     *
     * @param bool $cancelled
     */
    public function setCancelled($cancelled)
    {
        $this->setData('cancelled', $cancelled);
    }

    /**
     * Get the reviewer's request resent value.
     *
     * @return bool
     */
    public function getRequestResent()
    {
        return $this->getData('request_resent');
    }

    /**
     * Set the reviewer's request resent value.
     *
     * @param bool $resent
     */
    public function setRequestResent($resent)
    {
        $this->setData('request_resent', $resent);
    }

    /**
     * Get a boolean indicating whether or not the last reminder was automatic.
     *
     * @return bool
     */
    public function getReminderWasAutomatic()
    {
        return $this->getData('reminderWasAutomatic') == 1 ? 1 : 0;
    }

    /**
     * Set the boolean indicating whether or not the last reminder was automatic.
     *
     * @param bool $wasAutomatic
     */
    public function setReminderWasAutomatic($wasAutomatic)
    {
        $this->setData('reminderWasAutomatic', $wasAutomatic);
    }

    /**
     * Get quality.
     *
     * @return int|null
     */
    public function getQuality()
    {
        return $this->getData('quality');
    }

    /**
     * Set quality.
     *
     * @param int|null $quality
     */
    public function setQuality($quality)
    {
        $this->setData('quality', $quality);
    }

    /**
     * Get round.
     *
     * @return int
     */
    public function getRound()
    {
        return $this->getData('round');
    }

    /**
     * Set round.
     *
     * @param int $round
     */
    public function setRound($round)
    {
        $this->setData('round', $round);
    }

    /**
     * Get step.
     *
     * @return int
     */
    public function getStep()
    {
        return $this->getData('step');
    }

    /**
     * Set step.
     *
     * @param int $step
     */
    public function setStep($step)
    {
        $this->setData('step', $step);
    }

    /**
     * Get review form id.
     *
     * @return int
     */
    public function getReviewFormId()
    {
        return $this->getData('reviewFormId');
    }

    /**
     * Set review form id.
     *
     * @param int $reviewFormId
     */
    public function setReviewFormId($reviewFormId)
    {
        $this->setData('reviewFormId', $reviewFormId);
    }

    /**
     * Get the current status of this review assignment
     *
     * @return int ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_...
     */
    public function getStatus()
    {
        if ($this->getDeclined()) {
            return self::REVIEW_ASSIGNMENT_STATUS_DECLINED;
        }
        if ($this->getCancelled()) {
            return self::REVIEW_ASSIGNMENT_STATUS_CANCELLED;
        }

        if (!$this->getDeclined() && !$this->getDateConfirmed() && $this->getRequestResent()) {
            return self::REVIEW_ASSIGNMENT_STATUS_REQUEST_RESEND;
        }

        if (!$this->getDateCompleted()) {
            $dueTimes = array_map(function ($dateTime) {
                // If no due time, set it to the end of the day
                if (substr($dateTime, 11) === '00:00:00') {
                    $dateTime = substr($dateTime, 0, 11) . '23:59:59';
                }
                return strtotime($dateTime);
            }, [$this->getDateResponseDue(), $this->getDateDue()]);
            $responseDueTime = $dueTimes[0];
            $reviewDueTime = $dueTimes[1];
            if (!$this->getDateConfirmed()) { // no response
                if ($responseDueTime < time()) { // response overdue
                    return self::REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE;
                } elseif ($reviewDueTime < strtotime('tomorrow')) { // review overdue but not response
                    return self::REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE;
                } else { // response not due yet
                    return self::REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE;
                }
            } else { // response given
                if ($reviewDueTime < strtotime('tomorrow')) { // review due
                    return self::REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE;
                } else {
                    return self::REVIEW_ASSIGNMENT_STATUS_ACCEPTED;
                }
            }
        } elseif ($this->getDateAcknowledged()) { // reviewer thanked...
            if ($this->getConsidered() == self::REVIEW_ASSIGNMENT_UNCONSIDERED) { // ...but review later unconsidered
                return self::REVIEW_ASSIGNMENT_STATUS_RECEIVED;
            }
            return self::REVIEW_ASSIGNMENT_STATUS_THANKED;
        } elseif ($this->getDateCompleted()) { // review submitted...
            if ($this->getConsidered() != self::REVIEW_ASSIGNMENT_UNCONSIDERED && $this->isRead()) { // ...and confirmed by an editor
                return self::REVIEW_ASSIGNMENT_STATUS_COMPLETE;
            }
            return self::REVIEW_ASSIGNMENT_STATUS_RECEIVED;
        }

        return self::REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE;
    }

    /**
     * Determine whether an editorial user has read this review
     *
     * @return bool
     */
    public function isRead()
    {
        if($this->getConsidered() === self::REVIEW_ASSIGNMENT_CONSIDERED || $this->getConsidered() === self::REVIEW_ASSIGNMENT_RECONSIDERED) {
            return true;
        }

        return false;
    }

    /**
     * Get the translation key for the current status
     *
     * @param int $status Optionally pass a status to retrieve a specific key.
     *  Default will return the key for the current status.
     *
     * @return string
     */
    public function getStatusKey($status = null)
    {
        if (is_null($status)) {
            $status = $this->getStatus();
        }

        switch ($status) {
            case self::REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE:
                return 'submission.review.status.awaitingResponse';
            case self::REVIEW_ASSIGNMENT_STATUS_CANCELLED:
                return 'common.cancelled';
            case self::REVIEW_ASSIGNMENT_STATUS_DECLINED:
                return 'submission.review.status.declined';
            case self::REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE:
                return 'submission.review.status.responseOverdue';
            case self::REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE:
                return 'submission.review.status.reviewOverdue';
            case self::REVIEW_ASSIGNMENT_STATUS_ACCEPTED:
                return 'submission.review.status.accepted';
            case self::REVIEW_ASSIGNMENT_STATUS_RECEIVED:
                return 'submission.review.status.received';
            case self::REVIEW_ASSIGNMENT_STATUS_COMPLETE:
                return 'submission.review.status.complete';
            case self::REVIEW_ASSIGNMENT_STATUS_THANKED:
                return 'submission.review.status.thanked';
            case self::REVIEW_ASSIGNMENT_STATUS_REQUEST_RESEND:
                return 'submission.review.status.awaitingResponse';
        }

        assert(false, 'No status key could be found for ' . get_class($this) . ' on ' . __LINE__);

        return '';
    }

    /**
     * Get the translation key for the review method
     *
     * @param int|null $method Optionally pass a method to retrieve a specific key.
     *  Default will return the key for the current review method
     *
     * @return string
     */
    public function getReviewMethodKey($method = null)
    {
        if (is_null($method)) {
            $method = $this->getReviewMethod();
        }

        switch ($method) {
            case self::SUBMISSION_REVIEW_METHOD_OPEN:
                return 'editor.submissionReview.open';
            case self::SUBMISSION_REVIEW_METHOD_ANONYMOUS:
                return 'editor.submissionReview.anonymous';
            case self::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS:
                return 'editor.submissionReview.doubleAnonymous';
        }

        assert(false, 'No review method key could be found for ' . get_class($this) . ' on ' . __LINE__);

        return '';
    }

    //
    // Files
    //

    /**
     * Get number of weeks until review is due (or number of weeks overdue).
     *
     * @return int
     */
    public function getWeeksDue()
    {
        $dateDue = $this->getDateDue();
        if ($dateDue === null) {
            return null;
        }
        return round((strtotime($dateDue) - time()) / (86400 * 7.0));
    }

    /**
     * Get an associative array matching reviewer recommendation codes with locale strings.
     * (Includes default '' => "Choose One" string.)
     *
     * @return array recommendation => localeString
     */
    public static function getReviewerRecommendationOptions()
    {
        static $reviewerRecommendationOptions = [
            '' => 'common.chooseOne',
            self::SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT => 'reviewer.article.decision.accept',
            self::SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS => 'reviewer.article.decision.pendingRevisions',
            self::SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE => 'reviewer.article.decision.resubmitHere',
            self::SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE => 'reviewer.article.decision.resubmitElsewhere',
            self::SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE => 'reviewer.article.decision.decline',
            self::SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS => 'reviewer.article.decision.seeComments'
        ];
        return $reviewerRecommendationOptions;
    }

    /**
     * Return a localized string representing the reviewer recommendation.
     */
    public function getLocalizedRecommendation()
    {
        $options = self::getReviewerRecommendationOptions();
        if (array_key_exists($this->getRecommendation(), $options)) {
            return __($options[$this->getRecommendation()]);
        } else {
            return '';
        }
    }

    /**
     * Determine if can resend request to reconsider review for this review assignment
     */
    public function canResendReviewRequest(): bool
    {
        if ($this->getCancelled()) {
            return false;
        }

        if (!$this->getDeclined()) {
            return false;
        }

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\reviewAssignment\ReviewAssignment', '\ReviewAssignment');
    foreach ([
        'SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT',
        'SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS',
        'SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE',
        'SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE',
        'SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE',
        'SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS',
        'SUBMISSION_REVIEWER_RATING_VERY_GOOD',
        'SUBMISSION_REVIEWER_RATING_GOOD',
        'SUBMISSION_REVIEWER_RATING_AVERAGE',
        'SUBMISSION_REVIEWER_RATING_POOR',
        'SUBMISSION_REVIEWER_RATING_VERY_POOR',
        'SUBMISSION_REVIEW_METHOD_ANONYMOUS',
        'SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS',
        'SUBMISSION_REVIEW_METHOD_OPEN',
        'REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE',
        'REVIEW_ASSIGNMENT_STATUS_DECLINED',
        'REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE',
        'REVIEW_ASSIGNMENT_STATUS_ACCEPTED',
        'REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE',
        'REVIEW_ASSIGNMENT_STATUS_RECEIVED',
        'REVIEW_ASSIGNMENT_STATUS_COMPLETE',
        'REVIEW_ASSIGNMENT_STATUS_THANKED',
        'REVIEW_ASSIGNMENT_STATUS_CANCELLED',
        'REVIEW_ASSIGNMENT_STATUS_REQUEST_RESEND',
    ] as $constantName) {
        if (!defined($constantName)) {
            define($constantName, constant('\PKP\submission\reviewAssignment\ReviewAssignment::' . $constantName));
        }
    }
}
