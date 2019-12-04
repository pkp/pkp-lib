<?php

/**
* @file classes/statistics/SubmissionStatistics.inc.php
*
* Copyright (c) 2013-2019 Simon Fraser University
* Copyright (c) 2003-2019 John Willinsky
* Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
*
* @class SubmissionStatistics
* @ingroup statistics
*
* @brief Class responsible to keep submission statistics retrieved through the EditorialStatisticsService
*
*/

namespace PKP\Statistics;

class SubmissionStatistics {
	/** @var object An object containing all the harvested data */
	private $_data;

	/**
	 * Constructor
	 * @param $data stdClass Must receive the result from the SubmissionStatisticsQueryBuilder query builder
	 */
	public function __construct(\stdClass $data) {
		$this->_data = $data;
	}

	/**
	 * Retrieve the amount of active submissions
	 * @return int
	 */
	public function getActive() : int
	{
		return (int) $this->_data->active_total;
	}

	/**
	 * Retrieve the amount of active submissions in submission
	 * @return int
	 */
	public function getActiveInSubmission() : int
	{
		return (int) $this->_data->active_submission;
	}

	/**
	 * Retrieve the amount of active submissions in review
	 * @return int
	 */
	public function getActiveInInternalReview() : int
	{
		return (int) $this->_data->active_internal_review;
	}

	/**
	 * Retrieve the amount of active submissions in external review
	 * @return int
	 */
	public function getActiveInExternalReview() : int
	{
		return (int) $this->_data->active_external_review;
	}

	/**
	 * Retrieve the amount of active submissions in copyediting
	 * @return int
	 */
	public function getActiveInCopyEditing() : int
	{
		return (int) $this->_data->active_copy_editing;
	}

	/**
	 * Retrieve the amount of active submissions in production
	 * @return int
	 */
	public function getActiveInProduction() : int
	{
		return (int) $this->_data->active_production;
	}

	/**
	 * Retrieve the amount of received submissions
	 * @return int
	 */
	public function getReceived() : int
	{
		return (int) $this->_data->submission_received;
	}

	/**
	 * Retrieve the amount of accepted submissions
	 * @return int
	 */
	public function getAccepted() : int
	{
		return (int) $this->_data->submission_accepted;
	}

	/**
	 * Retrieve the amount of published submissions
	 * @return int
	 */
	public function getPublished() : int
	{
		return (int) $this->_data->submission_published;
	}

	/**
	 * Retrieve the amount of submissions declined in post-review
	 * @return int
	 */
	public function getDeclinedByPostReview() : int
	{
		return (int) $this->_data->submission_declined;
	}

	/**
	 * Retrieve the amount of submissions declined by desk-reject
	 * @return int
	 */
	public function getDeclinedByDeskReject() : int
	{
		return (int) $this->_data->submission_declined_initial;
	}

	/**
	 * Retrieve the amount of submissions declined by other reasons
	 * @return int
	 */
	public function getDeclinedByOtherReason() : int
	{
		return (int) $this->_data->submission_declined_other;
	}

	/**
	 * Retrieve the average amount of days to accept a submission
	 * @return float
	 */
	public function getAverageDaysToAccept() : float
	{
		return (float) $this->_data->submission_days_to_accept;
	}

	/**
	 * Retrieve the average amount of days to reject a submission
	 * @return float
	 */
	public function getAverageDaysToReject() : float
	{
		return (float) $this->_data->submission_days_to_reject;
	}

	/**
	 * Retrieve the average amount of days to receive the first decision
	 * @return float
	 */
	public function getAverageDaysToFirstDecision() : float
	{
		return (float) $this->_data->submission_days_to_first_decision;
	}

	/**
	 * Retrieve the average amount of days to decide
	 * @return float
	 */
	public function getAverageDaysToDecide() : float
	{
		return (float) $this->_data->submission_days_to_decide;
	}

	/**
	 * Retrieve the acceptance rate for the given period
	 * @return float
	 */
	public function getAcceptanceRate() : float
	{
		return (float) $this->_data->submission_acceptance_rate;
	}

	/**
	 * Retrieve the declined by post-review rate for the given period
	 * @return float
	 */
	public function getDeclinedByPostReviewRate() : float
	{
		return (float) $this->_data->submission_declined_rate;
	}

	/**
	 * Retrieve the declined by desk-reject rate for the given period
	 * @return float
	 */
	public function getDeclinedByDeskRejectRate() : float
	{
		return (float) $this->_data->submission_declined_initial_rate;
	}

	/**
	 * Retrieve the declined by other reasons rate for the given period
	 * @return float
	 */
	public function getDeclinedByOtherReasonRate() : float
	{
		return (float) $this->_data->submission_declined_other_rate;
	}

	/**
	 * Retrieve the amount of declined submissions
	 * @return int
	 */
	public function getDeclined() : int
	{
		return (int) $this->_data->submission_declined_total;
	}

	/**
	 * Retrieve the rejection rate for the given period
	 * @return float
	 */
	public function getRejectionRate() : float
	{
		return (float) $this->_data->submission_rejection_rate;
	}

	/**
	 * Retrieve the average amount of received submissions per year
	 * @return float
	 */
	public function getReceivedPerYear() : float
	{
		return (float) $this->_data->avg_submission_received;
	}

	/**
	 * Retrieve the average amount of accepted submissions per year
	 * @return float
	 */
	public function getAcceptedPerYear() : float
	{
		return (float) $this->_data->avg_submission_accepted;
	}

	/**
	 * Retrieve the average amount of published submissions per year
	 * @return float
	 */
	public function getPublishedPerYear() : float
	{
		return (float) $this->_data->avg_submission_published;
	}

	/**
	 * Retrieve the average amount of submissions declined by desk-reject per year
	 * @return float
	 */
	public function getDeclinedByDeskRejectPerYear() : float
	{
		return (float) $this->_data->avg_submission_declined_initial;
	}

	/**
	 * Retrieve the average amount of submissions declined by post-review per year
	 * @return float
	 */
	public function getDeclinedByPostReviewPerYear() : float
	{
		return (float) $this->_data->avg_submission_declined;
	}

	/**
	 * Retrieve the average amount of submissions declined by other reasons per year
	 * @return float
	 */
	public function getDeclinedByOtherReasonPerYear() : float
	{
		return (float) $this->_data->avg_submission_declined_other;
	}

	/**
	 * Retrieve the average amount of submissions declined per year
	 * @return float
	 */
	public function getDeclinedPerYear() : float
	{
		return (float) $this->_data->avg_submission_declined_total;
	}
}
