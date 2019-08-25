<?php

/**
 * @file classes/services/EditorialStatisticsService.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorialStatisticsService
 * @ingroup services
 *
 * @brief Helper class that encapsulates editorial statistics business logic
 */

namespace PKP\Services;

class EditorialStatisticsService {
	/**
	 * Retrieves user registrations statistics optionally filtered by context and date range
	 * @param $contextId int The context id
	 * @param $args array An array of filters accepting the keys dateStart and dateEnd
	 * @return array An array grouped by roles with the user statistics
	 */
	public function getUserStatistics($contextId = null, $args = []) {
		return \DAORegistry::getDAO('MetricsDAO')
			->getUserStatistics($contextId, $args['dateStart'] ?? null, $args['dateEnd'] ?? null);
	}

	/**
	 * Retrieves general statistics from the submissions optionally filtered by context, date range and section
	 * @param $contextId int The context id
	 * @param $args array An array of filters accepting the keys dateStart, dateEnd and sectionIds
	 * @return array An array with the submissions statistics
	 */
	public function getSubmissionStatistics($contextId = null, $args = []) {
		return \DAORegistry::getDAO('MetricsDAO')
			->getSubmissionStatistics($contextId, $args['dateStart'] ?? null, $args['dateEnd'] ?? null, $args['sectionIds'] ?? null);
	}

	/**
	 * Given an array with statistics compiles the required editorial information to display in the component
	 * @param $rangedStatistics array An array with a subset of the results from a call to EditorialStatisticsService::getSubmissionStatistics
	 * @param $statistics array An array with the results from a call to EditorialStatisticsService::getSubmissionStatistics
	 * @return array An array with the editorial information and statistics already indented for better visualization
	 */
	public static function compileEditorialStatistics($rangedStatistics, $statistics) {
		$editorialStatistics = [];
		$percentage = '%.2f%%';
		$defaultFormat = '%d';
		$indent = '&emsp;';
		foreach ([
			'SUBMISSION_RECEIVED' => [
				'name' => __('manager.statistics.editorial.submissionsReceived'),
				'averageField' => 'AVG_SUBMISSION_RECEIVED'
			],
			'SUBMISSION_ACCEPTED' => [
				'name' => __('manager.statistics.editorial.submissionsAccepted'),
				'averageField' => 'AVG_SUBMISSION_ACCEPTED'
			],
			'SUBMISSION_DECLINED_TOTAL' => [
				'name' => __('manager.statistics.editorial.submissionsDeclined'),
				'averageField' => 'AVG_SUBMISSION_DECLINED_TOTAL'
			],
			'SUBMISSION_DECLINED_INITIAL' => [
				'name' => $indent . __('manager.statistics.editorial.submissionsDeclined.deskReject'),
				'averageField' => 'AVG_SUBMISSION_DECLINED_INITIAL'
			],
			'SUBMISSION_DECLINED' => [
				'name' => $indent . __('manager.statistics.editorial.submissionsDeclined.postReview'),
				'averageField' => 'AVG_SUBMISSION_DECLINED'
			],
			'SUBMISSION_DECLINED_OTHER' => [
				'name' => $indent . __('manager.statistics.editorial.submissionsDeclined.other'),
				'averageField' => 'AVG_SUBMISSION_DECLINED_OTHER'
			],
			'SUBMISSION_PUBLISHED' => [
				'name' => __('manager.statistics.editorial.submissionsPublished'),
				'averageField' => 'AVG_SUBMISSION_PUBLISHED'
			],
			'SUBMISSION_DAYS_TO_FIRST_DECIDE' => [
				'name' => __('manager.statistics.editorial.averageDaysToDecide'),
			],
			'SUBMISSION_DAYS_TO_ACCEPT' => [
				'name' => $indent . __('manager.statistics.editorial.averageDaysToAccept'),
			],
			'SUBMISSION_DAYS_TO_REJECT' => [
				'name' => $indent . __('manager.statistics.editorial.averageDaysToReject'),
			],
			'SUBMISSION_ACCEPTANCE_RATE' => [
				'name' => __('manager.statistics.editorial.acceptanceRate'),
				'format' => $percentage
			],
			'SUBMISSION_REJECTION_RATE' => [
				'name' => __('manager.statistics.editorial.rejectionRate'),
				'format' => $percentage
			],
			'SUBMISSION_DECLINED_INITIAL_RATE' => [
				'name' => $indent . __('manager.statistics.editorial.deskRejectRate'),
				'format' => $percentage
			],
			'SUBMISSION_DECLINED_RATE' => [
				'name' => $indent . __('manager.statistics.editorial.postReviewRejectRate'),
				'format' => $percentage
			],
			'SUBMISSION_DECLINED_OTHER_RATE' => [
				'name' => $indent . __('manager.statistics.editorial.otherRejectRate'),
				'format' => $percentage
			]
		] as $field => $descriptor) {
			$format = $descriptor['format'] ?? $defaultFormat;
			$editorialStatistics[] = [
				'name' => $descriptor['name'],
				'period' => sprintf($format, $rangedStatistics[$field]),
				'average' => sprintf($format, $statistics[$descriptor['averageField'] ?? $field]),
				'total' => sprintf($format, $statistics[$field])
			];
		}
		return $editorialStatistics;
	}

	/**
	 * Given an array with statistics compiles the required user statistics to display in the component
	 * @param $rangedStatistics array An array with a subset of the results from a call to EditorialStatisticsService::getUserStatistics
	 * @param $statistics array An array with the results from a call to EditorialStatisticsService::getUserStatistics
	 * @return array An array where each row is a role followed by its statistics
	 */
	public static function compileUserStatistics($rangedStatistics, $statistics) {
		$userStatistics = [];
		foreach ([0 => 'manager.statistics.editorial.registeredUsers'] + \Application::getRoleNames(true) as $id => $role) {
			$userStatistics[] = [
				'name' => __($role),
				'period' => (int) $rangedStatistics[$id]['total'],
				'average' => round($statistics[$id]['average']),
				'total' => (int) $statistics[$id]['total']
			];
		}
		return $userStatistics;
	}

	/**
	 * Given an array with statistics compiles the required submission information to display in the chart component
	 * @param $statistics array An array with the results from a call to EditorialStatisticsService::getSubmissionStatistics
	 * @return array An array with the chart items
	 */
	public static function compileSubmissionChartData($statistics) {
		return [
			[
				'name' => __('manager.publication.submissionStage'),
				'value' => (int)$statistics['ACTIVE_SUBMISSION'],
				'color' => '#d00a0a',
			],
			[
				'name' => __('workflow.review.internalReview'),
				'value' => (int)$statistics['ACTIVE_INTERNAL_REVIEW'],
				'color' => '#e05c14',
			],
			[
				'name' => __('manager.statistics.editorial.externalReview'),
				'value' => (int)$statistics['ACTIVE_EXTERNAL_REVIEW'],
				'color' => '#e08914',
			],
			[
				'name' => __('submission.copyediting'),
				'value' => (int)$statistics['ACTIVE_EDITING'],
				'color' => '#007ab2',
			],
			[
				'name' => __('manager.publication.productionStage'),
				'value' => (int)$statistics['ACTIVE_PRODUCTION'],
				'color' => '#00b28d',
			]
		];
	}
}
