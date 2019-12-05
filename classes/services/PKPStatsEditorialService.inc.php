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
		$declined = $this->countByStatus(STATUS_DECLINED, $args);
		$declinedDesk = $this->countByDecisions(SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE, $args);
		$declinedReview = $this->countByDecisions(SUBMISSION_EDITOR_DECISION_DECLINE, $args);
		$declinedOther = $declined - $declinedDesk - $declinedReview;

		// Calculate the acceptance/decline rates
		if (!$received) {
			// Never divide by 0
			$acceptanceRate = 0;
			$declineRate = 0;
			$declinedDeskRate = 0;
			$declinedReviewRate = 0;
			$declinedOtherRate = 0;
		} elseif (empty($args['dateStart']) && empty($args['dateEnd'])) {
			$acceptanceRate = $accepted / $received;
			$declineRate = $declined / $received;
			$declinedDeskRate = $declinedDesk / $received;
			$declinedReviewRate = $declinedReview / $received;
			$declinedOtherRate = $declinedOther / $received;
		} else {
			$acceptanceRate = $this->countByDecisionsForSubmittedDate(SUBMISSION_EDITOR_DECISION_ACCEPT, $args) / $received;
			$declineRate = '';
			$declinedDeskRate = $this->countByDecisionsForSubmittedDate(SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE, $args) / $received;
			$declinedReviewRate = $this->countByDecisionsForSubmittedDate(SUBMISSION_EDITOR_DECISION_DECLINE, $args) / $received;
			$declinedOtherRate = '';
		}

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
				'key' => 'submissionsDeclinedOther',
				'name' => __('stats.name.submissionsDeclinedOther'),
				'value' => $declinedOther,
			],
			[
				'key' => 'submissionsPublished',
				'name' => __('stats.name.submissionsPublished'),
				'value' => $this->countSubmissionsPublished($args),
			],
			[
				'key' => 'averageDaysToDecision',
				'name' => __('stats.name.averageDaysToDecision'),
				'value' => $this->getAverageDaysToDecisions([], $args),
			],
			[
				'key' => 'averageDaysToAccept',
				'name' => __('stats.name.averageDaysToAccept'),
				'value' => $this->getAverageDaysToDecisions([SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION, SUBMISSION_EDITOR_DECISION_ACCEPT], $args),
			],
			[
				'key' => 'averageDaysToReject',
				'name' => __('stats.name.averageDaysToReject'),
				'value' => $this->getAverageDaysToDecisions([SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE], $args),
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
			[
				'key' => 'declinedOtherRate',
				'name' => __('stats.name.declinedOtherRate'),
				'value' => round($declinedOtherRate, 2),
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