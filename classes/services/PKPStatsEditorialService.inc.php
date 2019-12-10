<?php

/**
 * @file classes/services/PKPStatsEditorialService.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsEditorialService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for getting
 *   editorial stats
 */

namespace PKP\Services;

class PKPStatsEditorialService {

	/**
	 * Get overview of key editorial stats
	 *
	 * @param array $args See self::_getQueryBuilder()
	 * @return array
	 */
	public function getOverview($args = []) {
		import('classes.workflow.EditorDecisionActionsManager');
		import('lib.pkp.classes.submission.PKPSubmission');
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);
		\AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);

		$received = $this->countSubmissionsReceived($args);
		$accepted = $this->countByDecisions(SUBMISSION_EDITOR_DECISION_ACCEPT, $args);
		$declinedDesk = $this->countByDecisions(SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE, $args);
		$declinedReview = $this->countByDecisions(SUBMISSION_EDITOR_DECISION_DECLINE, $args);
		$declined = $declinedDesk + $declinedReview;

		// Calculate the acceptance/decline rates
		if (!$received) {
			// Never divide by 0
			$acceptanceRate = 0;
			$declineRate = 0;
			$declinedDeskRate = 0;
			$declinedReviewRate = 0;
		} elseif (empty($args['dateStart']) && empty($args['dateEnd'])) {
			$acceptanceRate = $accepted / $received;
			$declineRate = $declined / $received;
			$declinedDeskRate = $declinedDesk / $received;
			$declinedReviewRate = $declinedReview / $received;
		} else {
			// To calculate the acceptance/decline rates within a date range
			// we must collect the total number of all submissions made within
			// that date range which have received a decision. The acceptance
			// rate is the number of submissions made within the date range
			// that were accepted divided by the number of submissions made
			// within the date range that were accepted or declined. This
			// excludes submissions that were made within the date range but
			// have not yet been accepted or declined.
			$acceptedForSubmissionDate = $this->countByDecisionsForSubmittedDate(SUBMISSION_EDITOR_DECISION_ACCEPT, $args);
			$declinedDeskForSubmissionDate = $this->countByDecisionsForSubmittedDate(SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE, $args);
			$declinedReviewForSubmissionDate = $this->countByDecisionsForSubmittedDate(SUBMISSION_EDITOR_DECISION_DECLINE, $args);
			$totalDecidedForSubmissionDate = $acceptedForSubmissionDate + $declinedDeskForSubmissionDate + $declinedReviewForSubmissionDate;
			$acceptanceRate =  $acceptedForSubmissionDate / $totalDecidedForSubmissionDate;
			$declineRate = ($declinedDeskForSubmissionDate + $declinedReviewForSubmissionDate) / $totalDecidedForSubmissionDate;
			$declinedDeskRate =  $declinedDeskForSubmissionDate / $totalDecidedForSubmissionDate;
			$declinedReviewRate =  $declinedReviewForSubmissionDate / $totalDecidedForSubmissionDate;
		}

		// Calculate the number of days it took for most submissions to
		// receive decisions
		$firstDecisionDays = $this->getDaysToDecisions([], $args);
		$acceptDecisionDays = $this->getDaysToDecisions([SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION, SUBMISSION_EDITOR_DECISION_ACCEPT], $args);
		$declineDecisionDays = $this->getDaysToDecisions([SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE], $args);
		$firstDecisionDaysRate = empty($firstDecisionDays) ? 0 : $this->calculateDaysToDecisionRate($firstDecisionDays, 0.8);
		$acceptDecisionDaysRate = empty($acceptDecisionDays) ? 0 : $this->calculateDaysToDecisionRate($acceptDecisionDays, 0.8);
		$declineDecisionDaysRate = empty($declineDecisionDays) ? 0 : $this->calculateDaysToDecisionRate($declineDecisionDays, 0.8);

		$overview = [
			[
				'key' => 'submissionsReceived',
				'name' => __('stats.name.submissionsReceived'),
				'value' => $received,
			],
			[
				'key' => 'submissionsAccepted',
				'name' => __('stats.name.submissionsAccepted'),
				'value' => $accepted,
			],
			[
				'key' => 'submissionsDeclined',
				'name' => __('stats.name.submissionsDeclined'),
				'value' => $declined,
			],
			[
				'key' => 'submissionsDeclinedDeskReject',
				'name' => __('stats.name.submissionsDeclinedDeskReject'),
				'value' => $declinedDesk,
			],
			[
				'key' => 'submissionsDeclinedPostReview',
				'name' => __('stats.name.submissionsDeclinedPostReview'),
				'value' => $declinedReview,
			],
			[
				'key' => 'submissionsPublished',
				'name' => __('stats.name.submissionsPublished'),
				'value' => $this->countSubmissionsPublished($args),
			],
			[
				'key' => 'daysToDecision',
				'name' => __('stats.name.daysToDecision'),
				'value' => $firstDecisionDaysRate,
			],
			[
				'key' => 'daysToAccept',
				'name' => __('stats.name.daysToAccept'),
				'value' => $acceptDecisionDaysRate,
			],
			[
				'key' => 'daysToReject',
				'name' => __('stats.name.daysToReject'),
				'value' => $declineDecisionDaysRate,
			],
			[
				'key' => 'acceptanceRate',
				'name' => __('stats.name.acceptanceRate'),
				'value' => round($acceptanceRate, 2),
			],
			[
				'key' => 'declineRate',
				'name' => __('stats.name.declineRate'),
				'value' => round($declineRate, 2),
			],
			[
				'key' => 'declinedDeskRate',
				'name' => __('stats.name.declinedDeskRate'),
				'value' => round($declinedDeskRate, 2),
			],
			[
				'key' => 'declinedReviewRate',
				'name' => __('stats.name.declinedReviewRate'),
				'value' => round($declinedReviewRate, 2),
			],
		];

		\HookRegistry::call('EditorialStats::overview', [&$overview, $args]);

		return $overview;
	}

	/**
	 * Get a count of the number of submissions that have been received
	 *
	 * Any date restrictions will be applied to the submission date, so it
	 * will only count submissions completed within the date range.
	 *
	 * @param array $args See self::_getQueryBuilder()
	 * @return int
	 */
	public function countSubmissionsReceived($args = []) {
		return $this->_getQueryBuilder($args)->countSubmissionsReceived();
	}


	/**
	 * Get a count of the number of submissions that have been published
	 *
	 * Any date restrictions will be applied to the initial publication date,
	 * so it will only count submissions published within the date range.
	 *
	 * @param array $args See self::_getQueryBuilder()
	 * @return int
	 */
	public function countSubmissionsPublished($args = []) {
		return $this->_getQueryBuilder($args)->countPublished();
	}

	/**
	 * Get a count of the submissions receiving one or more editorial decisions
	 *
	 * Any date restrictions will be applied to the decision, so it will only
	 * count decisions that occurred within the date range.
	 *
	 * @param int|array $decisions One or more SUBMISSION_EDITOR_DECISION_*
	 * @param array $args See self::_getQueryBuilder()
	 * @return int
	 */
	public function countByDecisions($decisions, $args = []) {
		return $this->_getQueryBuilder($args)->countByDecisions((array) $decisions);
	}

	/**
	 * Get a count of the submissions receiving one or more editorial decisions
	 *
	 * Any date restrictions will be applied to the submission date, so it will
	 * only count submissions made within the date range which eventually received
	 * one of the decisions.
	 *
	 * @param int|array $decisions One or more SUBMISSION_EDITOR_DECISION_*
	 * @param array $args See self::_getQueryBuilder()
	 * @return int
	 */
	public function countByDecisionsForSubmittedDate($decisions, $args = []) {
		return $this->_getQueryBuilder($args)->countByDecisions((array) $decisions, true);
	}

	/**
	 * Get a count of the submissions with one or more statuses
	 *
	 * Date restrictions will not be applied. It will return the count of
	 * all submissions with the passed statuses.
	 *
	 * @param int|array $statuses One or more STATUS_*
	 * @param array $args See self::_getQueryBuilder()
	 * @return int
	 */
	public function countByStatus($statuses, $args = []) {
		return $this->_getQueryBuilder($args)->countByStatus((array) $statuses);
	}

	/**
	 * Get a count of the active submissions in one or more stages
	 *
	 * Date restrictions will not be applied. It will return the count of
	 * all submissions with the passed statuses.
	 *
	 * @param int|array $stages One or more WORKFLOW_STAGE_ID_*
	 * @param array $args See self::_getQueryBuilder()
	 * @return int
	 */
	public function countActiveByStages($stages, $args = []) {
		return $this->_getQueryBuilder($args)->countActiveByStages((array) $stages);
	}

	/**
	 * Get the number of days it took for each submission to reach
	 * one or more editorial decisions
	 *
	 * Any date restrictions will be applied to the submission date, so it will
	 * only return the days to a decision for submissions that were made within
	 * the selected date range.
	 *
	 * @param int|array $decisions One or more SUBMISSION_EDITOR_DECISION_*
	 * @param array $args See self::_getQueryBuilder()
	 * @return array
	 */
	public function getDaysToDecisions($decisions, $args = []) {
		return $this->_getQueryBuilder($args)->getDaysToDecisions((array) $decisions);
	}

	/**
	 * Get the average number of days to reach one or more editorial decisions
	 *
	 * Any date restrictions will be applied to the submission date, so it will
	 * only average the days to a decision for submissions that were made within
	 * the selected date range.
	 *
	 * @param int|array $decisions One or more SUBMISSION_EDITOR_DECISION_*
	 * @param array $args See self::_getQueryBuilder()
	 * @return int
	 */
	public function getAverageDaysToDecisions($decisions, $args = []) {
		return ceil($this->_getQueryBuilder($args)->getAverageDaysToDecisions((array) $decisions));
	}

	/**
	 * A helper function to calculate the number of days it took reach an
	 * editorial decision on a given portion of submission decisions
	 *
	 * This can be used to answer questions like how many days it took for
	 * a decision to be reached in 80% of submissions.
	 *
	 * For example, if passed an array of [5, 8, 10, 20] and a percentage of
	 * .75, it would return 10 since 75% of the array values are 10 or less.
	 *
	 * @param array $days An array of integers representing the dataset of
	 *  days to reach a decision.
	 * @param float $percentage The percentage of the dataset that must be
	 *  included in the rate. 75% = 0.75
	 * @return int The number of days X% of submissions received the decision
	 */
	public function calculateDaysToDecisionRate($days, $percentage) {
		sort($days);
		return end(array_slice($days, 0, ceil(count($days) * $percentage))) ?? 0;
	}

	/**
	 * Get a QueryBuilder object with the passed args
	 *
	 * @param array $args [
	 * 		@option string dateStart
	 *  	@option string dateEnd
	 *  	@option array|int contextIds
	 *    @option array|int sectionIds (will match seriesId in OMP)
	 * ]
	 * @return \APP\Services\QueryBuilders\StatsEditorialQueryBuilder
	 */
	protected function _getQueryBuilder($args = []) {
		$qb = new \APP\Services\QueryBuilders\StatsEditorialQueryBuilder();

		if (!empty($args['dateStart'])) {
			$qb->after($args['dateStart']);
		}
		if (!empty($args['dateEnd'])) {
			$qb->before($args['dateEnd']);
		}
		if (!empty($args['contextIds'])) {
			$qb->filterByContexts($args['contextIds']);
		}
		if (!empty($args['sectionIds'])) {
			$qb->filterBySections($args['sectionIds']);
		}

		\HookRegistry::call('Stats::editorial::queryBuilder', array($qb, $args));

		return $qb;
	}
}