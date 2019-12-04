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

use \PKP\Statistics\{UserStatistics, SubmissionStatistics};

class EditorialStatisticsService {
	public const USERS_ALL = 'USERS_ALL';
	public const SUBMISSIONS_RECEIVED = 'SUBMISSIONS_RECEIVED';
	public const SUBMISSIONS_ACCEPTED = 'SUBMISSIONS_ACCEPTED';
	public const SUBMISSIONS_DECLINED = 'SUBMISSIONS_DECLINED';
	public const SUBMISSIONS_DECLINED_DESK_REJECT = 'SUBMISSIONS_DECLINED_DESK_REJECT';
	public const SUBMISSIONS_DECLINED_POST_REVIEW = 'SUBMISSIONS_DECLINED_POST_REVIEW';
	public const SUBMISSIONS_DECLINED_OTHER = 'SUBMISSIONS_DECLINED_OTHER';
	public const SUBMISSIONS_PUBLISHED = 'SUBMISSIONS_PUBLISHED';
	public const SUBMISSIONS_DAYS_TO_DECIDE = 'SUBMISSIONS_DAYS_TO_DECIDE';
	public const SUBMISSIONS_DAYS_TO_ACCEPT = 'SUBMISSIONS_DAYS_TO_ACCEPT';
	public const SUBMISSIONS_DAYS_TO_REJECT = 'SUBMISSIONS_DAYS_TO_REJECT';
	public const SUBMISSIONS_ACCEPTANCE_RATE = 'SUBMISSIONS_ACCEPTANCE_RATE';
	public const SUBMISSIONS_REJECTION_RATE = 'SUBMISSIONS_REJECTION_RATE';
	public const SUBMISSIONS_REJECTION_RATE_DESK_REJECT = 'SUBMISSIONS_REJECTION_RATE_DESK_REJECT';
	public const SUBMISSIONS_REJECTION_RATE_POST_REVIEW = 'SUBMISSIONS_REJECTION_RATE_POST_REVIEW';
	public const SUBMISSIONS_REJECTION_RATE_OTHER = 'SUBMISSIONS_REJECTION_RATE_OTHER';
	public const ACTIVE_SUBMISSIONS_ACTIVE = 'ACTIVE_SUBMISSIONS_ACTIVE';
	public const ACTIVE_SUBMISSIONS_INTERNAL_REVIEW = 'ACTIVE_SUBMISSIONS_INTERNAL_REVIEW';
	public const ACTIVE_SUBMISSIONS_EXTERNAL_REVIEW = 'ACTIVE_SUBMISSIONS_EXTERNAL_REVIEW';
	public const ACTIVE_SUBMISSIONS_COPYEDITING = 'ACTIVE_SUBMISSIONS_COPYEDITING';
	public const ACTIVE_SUBMISSIONS_PRODUCTION = 'ACTIVE_SUBMISSIONS_PRODUCTION';

	/**
	 * Retrieves user registrations statistics optionally filtered by context and date range
	 * @param $contextId int The context id
	 * @param $args array An array of filters accepting the keys dateStart and dateEnd
	 * @return \PKP\Statistics\UserStatistics
	 */
	public function getUserStatistics(int $contextId = null, array $args = []) : UserStatistics
	{
		$builder = new QueryBuilders\UserStatisticsQueryBuilder();
		$data = $builder
			->withContext($contextId)
			->withDateRange($args['dateStart'] ?? null, $args['dateEnd'] ?? null)
			->build()
			->get();
		return new UserStatistics($data);
	}

	/**
	 * Retrieves general statistics from the submissions optionally filtered by context, date range and section
	 * @param $contextId int The context id
	 * @param $args array An array of filters accepting the keys dateStart, dateEnd and sectionIds
	 * @return \PKP\Statistics\SubmissionStatistics
	 */
	public function getSubmissionStatistics(int $contextId = null, array $args = []) : SubmissionStatistics
	{
		$builder = new QueryBuilders\SubmissionStatisticsQueryBuilder();
		$data = $builder
			->withContext($contextId)
			->withDateRange($args['dateStart'] ?? null, $args['dateEnd'] ?? null)
			->withSections($args['sectionIds'] ?? null)
			->build()
			->get();
		return new SubmissionStatistics($data->first());
	}

	/**
	 * Compiles a summary of the user registrations given total and date ranged statistics
	 * @param $rangedStatistics \PKP\Statistics\UserStatistics
	 * @param $statistics \PKP\Statistics\UserStatistics
	 * @return array A keyed array where the key represents the role ID, and value the compiled data
	 */
	public static function compileUsers(UserStatistics $rangedStatistics, UserStatistics $statistics) : array
	{
		$userStatistics = [
			self::USERS_ALL => [
				'name' => __('manager.statistics.users.allRoles'),
				'period' => $rangedStatistics->getRegistrations(),
				'average' => round($statistics->getRegistrationsPerYear()),
				'total' => $statistics->getRegistrations()
			]
		];
		foreach (\Application::getRoleNames(true) as $roleId => $role) {
			$userStatistics[$roleId] = [
				'name' => __($role),
				'period' => $rangedStatistics->getRegistrationsByRole($roleId),
				'total' => $statistics->getRegistrationsByRole($roleId)
			];
		}
		return $userStatistics;
	}

	/**
	 * Compiles a summary of the all the submissions given a total and date ranged statistics
	 * @param $rangedStatistics \PKP\Statistics\SubmissionStatistics
	 * @param $statistics \PKP\Statistics\SubmissionStatistics
	 * @return array A keyed array where the key is a SUBMISSIONS_* class const, and value the compiled data
	 */
	public static function compileSubmissions(SubmissionStatistics $rangedStatistics, SubmissionStatistics $statistics) : array
	{
		$percentage = '%.2f%%';
		$integer = '%d';
		$indent = '&emsp;';
		
		$itemFactory = function($name, $valueGenerator, $format) {
			return [
				'name' => $name,
				'value' => $valueGenerator,
				'format' => $format
			];
		};

		$items = [
			self::SUBMISSIONS_RECEIVED => $itemFactory(
				__('manager.statistics.editorial.submissionsReceived'),
				function ($source, $type) {
					return $type == 'average' ? $source->getReceivedPerYear() : $source->getReceived();
				},
				$integer
			),
			self::SUBMISSIONS_ACCEPTED => $itemFactory(
				__('manager.statistics.editorial.submissionsAccepted'),
				function ($source, $type) {
					return $type == 'average' ? $source->getAcceptedPerYear() : $source->getAccepted();
				},
				$integer
			),
			self::SUBMISSIONS_DECLINED => $itemFactory(
				__('manager.statistics.editorial.submissionsDeclined'),
				function ($source, $type) {
					return $type == 'average' ? $source->getDeclinedPerYear() : $source->getDeclined();
				},
				$integer
			),
			self::SUBMISSIONS_DECLINED_DESK_REJECT => $itemFactory(
				$indent . __('manager.statistics.editorial.submissionsDeclined.deskReject'),
				function ($source, $type) {
					return $type == 'average' ? $source->getDeclinedByDeskRejectPerYear() : $source->getDeclinedByDeskReject();
				},
				$integer
			),
			self::SUBMISSIONS_DECLINED_POST_REVIEW => $itemFactory(
				$indent . __('manager.statistics.editorial.submissionsDeclined.postReview'),
				function ($source, $type) {
					return $type == 'average' ? $source->getDeclinedByPostReviewPerYear() : $source->getDeclinedByPostReview();
				},
				$integer
			),
			self::SUBMISSIONS_DECLINED_OTHER => $itemFactory(
				$indent . __('manager.statistics.editorial.submissionsDeclined.other'),
				function ($source, $type) {
					return $type == 'average' ? $source->getDeclinedByOtherReasonPerYear() : $source->getDeclinedByOtherReason();
				},
				$integer
			),
			self::SUBMISSIONS_PUBLISHED => $itemFactory(
				__('manager.statistics.editorial.submissionsPublished'),
				function ($source, $type) {
					return $type == 'average' ? $source->getPublishedPerYear() : $source->getPublished();
				},
				$integer
			),
			self::SUBMISSIONS_DAYS_TO_DECIDE => $itemFactory(
				__('manager.statistics.editorial.averageDaysToDecide'),
				function ($source) {
					return $source->getAverageDaysToFirstDecision();
				},
				$integer
			),
			self::SUBMISSIONS_DAYS_TO_ACCEPT => $itemFactory(
				$indent . __('manager.statistics.editorial.averageDaysToAccept'),
				function ($source) {
					return $source->getAverageDaysToAccept();
				},
				$integer
			),
			self::SUBMISSIONS_DAYS_TO_REJECT => $itemFactory(
				$indent . __('manager.statistics.editorial.averageDaysToReject'),
				function ($source) {
					return $source->getAverageDaysToReject();
				},
				$integer
			),
			self::SUBMISSIONS_ACCEPTANCE_RATE => $itemFactory(
				__('manager.statistics.editorial.acceptanceRate'),
				function ($source) {
					return $source->getAcceptanceRate();
				},
				$percentage
			),
			self::SUBMISSIONS_REJECTION_RATE => $itemFactory(
				__('manager.statistics.editorial.rejectionRate'),
				function ($source) {
					return $source->getRejectionRate();
				},
				$percentage
			),
			self::SUBMISSIONS_REJECTION_RATE_DESK_REJECT => $itemFactory(
				$indent . __('manager.statistics.editorial.deskRejectRejectionRate'),
				function ($source) {
					return $source->getDeclinedByDeskRejectRate();
				},
				$percentage
			),
			self::SUBMISSIONS_REJECTION_RATE_POST_REVIEW => $itemFactory(
				$indent . __('manager.statistics.editorial.postReviewRejectionRate'),
				function ($source) {
					return $source->getDeclinedByPostReviewRate();
				},
				$percentage
			),
			self::SUBMISSIONS_REJECTION_RATE_OTHER => $itemFactory(
				$indent . __('manager.statistics.editorial.otherRejectionRate'),
				function ($source) {
					return $source->getDeclinedByOtherReasonRate();
				},
				$percentage
			)
		];
		$return = [];
		foreach ($items as $key => ['name' => $name, 'value' => $value, 'format' => $format]) {
			$return[$key] = [
				'name' => $name,
				'period' => sprintf($format, $value($rangedStatistics, 'period')),
				'total' => sprintf($format, $value($statistics, 'total'))
			];
		}
		return $return;
	}

	/**
	 * Compiles a summary of the active submissions
	 * @param $statistics \PKP\Statistics\SubmissionStatistics
	 * @return array A keyed array where the key is a ACTIVE_SUBMISSIONS_* class const, and value the compiled data
	 */
	public static function compileActiveSubmissions(SubmissionStatistics $statistics) : array
	{
		$activeSubmissions = [];
		$supportedStages = \Application::getApplicationStages();
		foreach ($supportedStages as $stageId) {
			switch ($stageId) {
				case WORKFLOW_STAGE_ID_SUBMISSION:
					$activeSubmissions[self::ACTIVE_SUBMISSIONS_ACTIVE] = [
						'name' => __('manager.publication.submissionStage'),
						'value' => $statistics->getActiveInSubmission()
					];
					break;
				case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
					$activeSubmissions[self::ACTIVE_SUBMISSIONS_INTERNAL_REVIEW] = [
						'name' => __('workflow.review.internalReview'),
						'value' => $statistics->getActiveInInternalReview()
					];
					break;
				case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
					$activeSubmissions[self::ACTIVE_SUBMISSIONS_EXTERNAL_REVIEW] = [
						'name' => __('workflow.review.externalReview'),
						'value' => $statistics->getActiveInExternalReview()
					];
					break;
				case WORKFLOW_STAGE_ID_EDITING:
					$activeSubmissions[self::ACTIVE_SUBMISSIONS_COPYEDITING] = [
						'name' => __('submission.copyediting'),
						'value' => $statistics->getActiveInCopyEditing()
					];
					break;
				case WORKFLOW_STAGE_ID_PRODUCTION:
					$activeSubmissions[self::ACTIVE_SUBMISSIONS_PRODUCTION] = [
						'name' => __('manager.publication.productionStage'),
						'value' => $statistics->getActiveInProduction()
					];
					break;
			}
		}
		return $activeSubmissions;
	}
}
