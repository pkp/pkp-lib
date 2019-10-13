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
				'name' => __('manager.statistics.editorial.registeredUsers'),
				'period' => $rangedStatistics->getRegistrations(),
				'average' => round($statistics->getRegistrationsPerYear()),
				'total' => $statistics->getRegistrations()
			]
		];
		foreach (\Application::getRoleNames(true) as $roleId => $role) {
			$userStatistics[$roleId] = [
				'name' => __($role),
				'period' => $rangedStatistics->getRegistrationsByRole($roleId),
				'average' => round($statistics->getRegistrationsByRolePerYear($roleId)),
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
		$defaultFormat = '%d';
		$indent = '&emsp;';

		$items = [
			self::SUBMISSIONS_RECEIVED => [
				'name' => __('manager.statistics.editorial.submissionsReceived'),
				'value' => function ($source, $type) {
					return $type == 'average' ? $source->getReceivedPerYear() : $source->getReceived();
				}
			],
			self::SUBMISSIONS_ACCEPTED => [
				'name' => __('manager.statistics.editorial.submissionsAccepted'),
				'value' => function ($source, $type) {
					return $type == 'average' ? $source->getAcceptedPerYear() : $source->getAccepted();
				}
			],
			self::SUBMISSIONS_DECLINED => [
				'name' => __('manager.statistics.editorial.submissionsDeclined'),
				'value' => function ($source, $type) {
					return $type == 'average' ? $source->getDeclinedPerYear() : $source->getDeclined();
				}
			],
			self::SUBMISSIONS_DECLINED_DESK_REJECT => [
				'name' => $indent . __('manager.statistics.editorial.submissionsDeclined.deskReject'),
				'value' => function ($source, $type) {
					return $type == 'average' ? $source->getDeclinedByDeskRejectPerYear() : $source->getDeclinedByDeskReject();
				}
			],
			self::SUBMISSIONS_DECLINED_POST_REVIEW => [
				'name' => $indent . __('manager.statistics.editorial.submissionsDeclined.postReview'),
				'value' => function ($source, $type) {
					return $type == 'average' ? $source->getDeclinedByPostReviewPerYear() : $source->getDeclinedByPostReview();
				}
			],
			self::SUBMISSIONS_DECLINED_OTHER => [
				'name' => $indent . __('manager.statistics.editorial.submissionsDeclined.other'),
				'value' => function ($source, $type) {
					return $type == 'average' ? $source->getDeclinedByOtherReasonPerYear() : $source->getDeclinedByOtherReason();
				}
			],
			self::SUBMISSIONS_PUBLISHED => [
				'name' => __('manager.statistics.editorial.submissionsPublished'),
				'value' => function ($source, $type) {
					return $type == 'average' ? $source->getPublishedPerYear() : $source->getPublished();
				}
			],
			self::SUBMISSIONS_DAYS_TO_DECIDE => [
				'name' => __('manager.statistics.editorial.averageDaysToDecide'),
				'value' => function ($source) {
					return $source->getAverageDaysToFirstDecision();
				}
			],
			self::SUBMISSIONS_DAYS_TO_ACCEPT => [
				'name' => $indent . __('manager.statistics.editorial.averageDaysToAccept'),
				'value' => function ($source) {
					return $source->getAverageDaysToAccept();
				}
			],
			self::SUBMISSIONS_DAYS_TO_REJECT => [
				'name' => $indent . __('manager.statistics.editorial.averageDaysToReject'),
				'value' => function ($source) {
					return $source->getAverageDaysToReject();
				}
			],
			self::SUBMISSIONS_ACCEPTANCE_RATE => [
				'name' => __('manager.statistics.editorial.acceptanceRate'),
				'value' => function ($source) {
					return $source->getAcceptanceRate();
				},
				'format' => $percentage
			],
			self::SUBMISSIONS_REJECTION_RATE => [
				'name' => __('manager.statistics.editorial.rejectionRate'),
				'value' => function ($source) {
					return $source->getRejectionRate();
				},
				'format' => $percentage
			],
			self::SUBMISSIONS_REJECTION_RATE_DESK_REJECT => [
				'name' => $indent . __('manager.statistics.editorial.deskRejectRejectionRate'),
				'value' => function ($source) {
					return $source->getDeclinedByDeskRejectRate();
				},
				'format' => $percentage
			],
			self::SUBMISSIONS_REJECTION_RATE_POST_REVIEW => [
				'name' => $indent . __('manager.statistics.editorial.postReviewRejectionRate'),
				'value' => function ($source) {
					return $source->getDeclinedByPostReviewRate();
				},
				'format' => $percentage
			],
			self::SUBMISSIONS_REJECTION_RATE_OTHER => [
				'name' => $indent . __('manager.statistics.editorial.otherRejectionRate'),
				'value' => function ($source) {
					return $source->getDeclinedByOtherReasonRate();
				},
				'format' => $percentage
			]
		];
		$return = [];
		foreach ($items as $key => $item) {
			['name' => $name, 'value' => $value, 'format' => $format] = $item;
			$format = $format ?? $defaultFormat;
			$return[$key] = [
				'name' => $name,
				'period' => sprintf($format, $value($rangedStatistics, 'period')),
				'average' => sprintf($format, $value($statistics, 'average')),
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
		return [
			self::ACTIVE_SUBMISSIONS_ACTIVE => [
				'name' => __('manager.publication.submissionStage'),
				'value' => $statistics->getActiveInSubmission()
			],
			self::ACTIVE_SUBMISSIONS_INTERNAL_REVIEW => [
				'name' => __('workflow.review.internalReview'),
				'value' => $statistics->getActiveInInternalReview(),
			],
			self::ACTIVE_SUBMISSIONS_EXTERNAL_REVIEW => [
				'name' => __('manager.statistics.editorial.externalReview'),
				'value' => $statistics->getActiveInExternalReview(),
			],
			self::ACTIVE_SUBMISSIONS_COPYEDITING => [
				'name' => __('submission.copyediting'),
				'value' => $statistics->getActiveInCopyEditing(),
			],
			self::ACTIVE_SUBMISSIONS_PRODUCTION => [
				'name' => __('manager.publication.productionStage'),
				'value' => $statistics->getActiveInProduction(),
			]
		];
	}
}
