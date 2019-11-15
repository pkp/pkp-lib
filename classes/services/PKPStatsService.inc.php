<?php

/**
 * @file classes/services/PKPStatsService.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsService
 * @ingroup services
 *
 * @brief Helper class that encapsulates statistics business logic
 */

namespace PKP\Services;

use \PKP\Services\EntityProperties\PKPBaseEntityPropertyService;
use \DAORegistry;


class PKPStatsService extends PKPBaseEntityPropertyService {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct($this);
	}

	/**
	 * Get statistics records of the submissions (filtered by request parameters),
	 * ordered by total stats numbers of their abstract and galley views.
	 *
	 * @param int $contextId
	 * @param array $args {
	 * 		@option string orderBy
	 * 		@option string orderDirection
	 * 		@option int count
	 * 		@option int offset
	 *		@option string timeSegment
	 *		@option string dateStart
	 *		@option string dateEnd
	 *		@option array sectionIds
	 *		@option array searchPhrase
	 * }
	 *
	 * @return array
	 */
	public function getOrderedSubmissions($contextId, $args = array()) {
		$records = array();
		if (!empty($args['searchPhrase'])) {
		    $submissionIds = $this->_filterSubmissionsBySearchPhrase($contextId, $args['searchPhrase']);
		    if (empty($submissionIds)) return $records;
		}
		$statsListQB = $this->_buildGetOrderedSubmissionsQueryObject($contextId, $args);
		if (isset($submissionIds)) {
		    $statsListQB->filterBySubmissionIds($submissionIds);
		}
		$statsQO = $statsListQB->get();
		if ($statsQO) {
			$metricsDao = \DAORegistry::getDAO('MetricsDAO');
			$result = $metricsDao->retrieve($statsQO->toSql(), $statsQO->getBindings());
			$records = $result->GetAll();
		}
		return $records;
	}

	/**
	 * Get the total counts of abstract and file views, as well as
	 * the totals broken down by time segment,
	 * for all submission statistics records (filtered by request parameters)
	 *
	 * @param int $contextId
	 * @param array $args {
	 * 		@option string orderBy
	 * 		@option string orderDirection
	 * 		@option int count
	 * 		@option int offset
	 *		@option string timeSegment
	 *		@option string dateStart
	 *		@option string dateEnd
	 *		@option array sectionIds
	 *		@option array searchPhrase
	 * }
	 *
	 * @return array
	 */
	public function getTotalSubmissionsStats($contextId, $args = array()) {
		$records = array();
		if (!empty($args['searchPhrase'])) {
			$submissionIds = $this->_filterSubmissionsBySearchPhrase($contextId, $args['searchPhrase']);
			if (empty($submissionIds)) return $records;
		}
		$statsListQB = $this->_buildGetTotalSubmissionsStatsQueryObject($contextId, $args);
		if (isset($submissionIds)) {
			$statsListQB->filterBySubmissionIds($submissionIds);
		}
		$statsQO = $statsListQB->get();
		if ($statsQO) {
			$metricsDao = \DAORegistry::getDAO('MetricsDAO');
			$result = $metricsDao->retrieve($statsQO->toSql(), $statsQO->getBindings());
			$records = $result->GetAll();
		}
		return $records;
	}

	/**
	 * @see \PKP\Services\EntityProperties\EntityPropertyInterface::getProperties()
	 *
	 * @param $object object Submission
	 * @param $props array
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 *
	 * @return array
	 */
	public function getProperties($object, $props, $args = null) {
		assert(is_a($object, 'Submission'));
		$entityService = \ServicesContainer::instance()->get('submission');
		$params = array(
			'entityAssocType' => ASSOC_TYPE_SUBMISSION,
			'fileAssocType' => ASSOC_TYPE_SUBMISSION_FILE,
		);
		$statsListQB = $this->_buildGetSubmissionQueryObject($object->getContextId(), $object->getId(), $args['params']);
		$statsQO = $statsListQB->get();

		$metricsDao = \DAORegistry::getDAO('MetricsDAO');
		$result = $metricsDao->retrieve($statsQO->toSql(), $statsQO->getBindings());
		$records = $result->GetAll();

		$values = $this->getRecordProperties($records, $params, $props, $args);
		$values['object'] = $entityService->getStatsObjectSummaryProperties($object, $args);

		\HookRegistry::call('Stats::getProperties', array(&$values, $object, $props, $args));

		return $values;
	}

	/**
	 * @see \PKP\Services\EntityProperties\EntityPropertyInterface::getSummaryProperties()
	 *
	 * @param $object object Submission
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 *
	 * @return array
	 */
	public function getSummaryProperties($object, $args = null) {
		$props = array (
			'total', 'abstractViews', 'totalFileViews', 'pdf', 'html', 'other', 'timeSegments',
		);

		\HookRegistry::call('Stats::getProperties::summaryProperties', array(&$props, $object, $args));

		return $this->getProperties($object, $props, $args);
	}

	/**
	 * @see \PKP\Services\EntityProperties\EntityPropertyInterface::getFullProperties()
	 * @param $object object Submission
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 * @return array
	 */
	public function getFullProperties($object, $args = null) {
		$props = array (
			'total', 'abstractViews', 'totalFileViews', 'pdf', 'html', 'other', 'timeSegments',
		);

		\HookRegistry::call('Stats::getProperties::fullProperties', array(&$props, $object, $args));

		return $this->getProperties($object, $props, $args);
	}

	/**
	 * Get properties for the total stats
	 *
	 * @param $records array
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 *
	 * @return array
	 */
	public function getTotalStatsProperties($records, $args = null) {
		$props = array (
			'total', 'abstractViews', 'totalFileViews', 'timeSegments',
		);

		\HookRegistry::call('Stats::getProperties::totalStatsProperties', array(&$props, $records, $args));

		$params = array(
			'entityAssocType' => ASSOC_TYPE_SUBMISSION,
			'fileAssocType' => ASSOC_TYPE_SUBMISSION_FILE,
		);
		return $this->getRecordProperties($records, $params, $props, $args);
	}

	/**
	 * Returns all time segments and their stats
	 *
	 * @param $records array
	 * @param $params array
	 * 		@option int entityAssocType
	 * 		@option int fileAssocType
	 * @param $props array
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 *
	 * @return array
	 */
	public function getTimeSegments($records, $params, $props, $args = null) {
		// get the requested and used time segment (month or day)
		if (isset($args['params']['timeSegment'])) {
			$timeSegment = $args['params']['timeSegment'] == 'day' ? STATISTICS_DIMENSION_DAY : STATISTICS_DIMENSION_MONTH;
		} else {
			$timeSegment = STATISTICS_DIMENSION_MONTH;
		}

		// get the earliest found date (month or day), in format Ymd
		$earliestDateFound = $timeSegment == STATISTICS_DIMENSION_MONTH ? end($records)[$timeSegment] . '01' : end($records)[$timeSegment];
		// get the start and end date:
		// if dateStart parameter exists take it, else take the earliest found date
		$dateStart = isset($args['params']['dateStart']) ? str_replace('-', '', $args['params']['dateStart']) : $earliestDateFound;
		// if dateEnd parameter exists take it, else take the current date
		$dateEnd = isset($args['params']['dateEnd']) ? str_replace('-', '', $args['params']['dateEnd']) : date('Ymd', time());
		// get all time segments (months or days) between the start and end date
		// the result dates are then sorted DESC (latest -> earliest)
		$timeSegmentDates = array_reverse($this->_getTimeSegments($dateStart, $dateEnd, $timeSegment));

		// Get stats for all existing days (months or days), and
		// prepare the timeSegments return values (date, dateLabel and the actual stats for each time segment)
		$allTimeSegments = $timeSegmentsStats = array();
		foreach ($timeSegmentDates as $timeSegmentDate) {
			// all stats we are interested in
			$total = $abstractViews = $fileViews = $pdfs = $htmls = $others = 0;

			// get all records with the given date
			// if the given date is not found, an empty array will be the result
			$dateRecords = array_filter($records, function ($record) use ($timeSegmentDate, $timeSegment) {
				return ($record[$timeSegment] == $timeSegmentDate);
			});

			$timeSegmentsStats[$timeSegmentDate] = $this->getStatsProperties($dateRecords, $params, $props);

			// get time segment date label
			if ($timeSegment == STATISTICS_DIMENSION_MONTH) {
				$dateLabel = strftime('%B %Y', strtotime($timeSegmentDate . '01'));
			} elseif ($timeSegment == STATISTICS_DIMENSION_DAY) {
				$dateLabel = strftime(\Config::getVar('general', 'date_format_long'), strtotime($timeSegmentDate));
			}

			// time segments values to be returned
			$allTimeSegments[] = array_merge(array(
					'date' => $timeSegmentDate,
					'dateLabel' => $dateLabel
				),
				$timeSegmentsStats[$timeSegmentDate]
			);
		}
		return $allTimeSegments;
	}

	/**
	 * Returns values given a list of properties of the stats record
	 *
	 * @param $records array
	 * @param $params array
	 * 		@option int entityAssocType
	 * 		@option int fileAssocType
	 * @param $props array
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 *
	 * @return array
	 */
	public function getRecordProperties($records, $params, $props, $args = null) {
		$values = $this->getStatsProperties($records, $params, $props, $args);
		if (in_array('timeSegments', $props)) {
			$timeSegmentProps = $props;
			unset($timeSegmentProps['timeSegments']);
			$values['timeSegments'] = $this->getTimeSegments($records, $params, $timeSegmentProps, $args);
		}
		return $values;
	}

	/**
	 * Returns stats values given a list of properties of the stats record
	 *
	 * @param $records array
	 * @param $params array
	 * 		@option int entityAssocType
	 * 		@option int fileAssocType
	 * @param $props array
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 *
	 * @return array
	 */
	public function getStatsProperties($records, $params, $props, $args = null) {
		$values = array();
		// get entity (Submission) and file assoc type
		// used for abstract and file views
		$entityAssocType = $params['entityAssocType'];
		$fileAssocType = $params['fileAssocType'];
		foreach ($props as $prop) {
			switch ($prop) {
				case 'total':
					if (empty($records)) {
						$values[$prop] = 0;
					} else {
						// total = sum of all records
						$values[$prop] = array_sum(array_map(
							function($record){
								return $record[STATISTICS_METRIC];
							},
							$records
						));
					}
					break;
				case 'abstractViews':
					if (empty($records)) {
						$values[$prop] = 0;
					} else {
						// filter abstract views records (containing the entity assoc type)
						$abstractViewsRecords = array_filter($records, function ($record) use ($entityAssocType) {
							return ($record[STATISTICS_DIMENSION_ASSOC_TYPE] == $entityAssocType);
						});
						// abstract views = sum of all abstract views records
						$values[$prop] = array_sum(array_map(
							function($record){
								return $record[STATISTICS_METRIC];
							},
							$abstractViewsRecords
						));
					}
					break;
				case 'totalFileViews':
					if (empty($records)) {
						$values[$prop] = 0;
					} else {
						// filter file views records (containing the file assoc type)
						$fileViewsRecords = array_filter($records, function ($record) use ($fileAssocType) {
							return ($record[STATISTICS_DIMENSION_ASSOC_TYPE] == $fileAssocType);
						});
						// total file views = sum of all total file views records
						$values[$prop] = array_sum(array_map(
							function($record){
								return $record[STATISTICS_METRIC];
							},
							$fileViewsRecords
						));
					}
					break;
				case 'pdf':
					if (empty($records)) {
						$values[$prop] = 0;
					} else {
						// filter pdf views records (containing the file assoc type and the pdf file type)
						$pdfRecords = array_filter($records, function ($record) use ($fileAssocType) {
							return ($record[STATISTICS_DIMENSION_ASSOC_TYPE] == $fileAssocType && $record[STATISTICS_DIMENSION_FILE_TYPE] == STATISTICS_FILE_TYPE_PDF);
						});
						//  pdf views = sum of all pdf views records
						$values[$prop] = array_sum(array_map(
							function($record){
								return $record[STATISTICS_METRIC];
							},
							$pdfRecords
						));
					}
					break;
				case 'html':
					if (empty($records)) {
						$values[$prop] = 0;
					} else {
						// filter html views records (containing the file assoc type and the html file type)
						$htmlRecords = array_filter($records, function ($record) use ($fileAssocType) {
							return ($record[STATISTICS_DIMENSION_ASSOC_TYPE] == $fileAssocType && $record[STATISTICS_DIMENSION_FILE_TYPE] == STATISTICS_FILE_TYPE_HTML);
						});
						// html views = sum of all html views records
						$values[$prop] = array_sum(array_map(
							function($record){
								return $record[STATISTICS_METRIC];
							},
							$htmlRecords
						));
					}
					break;
				case 'other':
					if (empty($records)) {
						$values[$prop] = 0;
					} else {
						// filter other file tpye views records (containing the file assoc type and the other file type)
						$otherRecords = array_filter($records, function ($record) use ($fileAssocType) {
							return ($record[STATISTICS_DIMENSION_ASSOC_TYPE] == $fileAssocType && $record[STATISTICS_DIMENSION_FILE_TYPE] == STATISTICS_FILE_TYPE_OTHER);
						});
						// calculate the other file type views = sum of all other file type views for the current date
						$values[$prop] = array_sum(array_map(
							function($record){
								return $record[STATISTICS_METRIC];
							},
							$otherRecords
						));
					}
					break;
			}
		}

		return $values;
	}

	/**
	 * Build the stats query object for getOrderedSubmissions requests
	 *
	 * @see self::getOrderedSubmissions()
	 *
	 * @return object Query object
	 */
	private function _buildGetOrderedSubmissionsQueryObject($contextId, $args = array()) {
		$defaultArgs = array(
			'orderBy' => 'total',
			'orderDirection' =>  'DESC',
		);
		$args = array_merge($defaultArgs, $args);

		$statsListQB = new \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder($contextId);
		$statsListQB
			->lookingFor('orderedSubmissions')
			->orderColumn($args['orderBy'])
			->orderDirection($args['orderDirection']);

		// filter by (OJS) section i.e. (OMP) series IDs
		if (isset($args['sectionIds'])) $statsListQB->filterBySectionIds($args['sectionIds']);

		$dateStart = isset($args['dateStart']) ? $args['dateStart'] : null;
		$dateEnd = isset($args['dateEnd']) ? $args['dateEnd'] : null;
		if ($dateStart || $dateEnd) {
			$statsListQB->filterByDateRange($dateStart, $dateEnd);
		}

		\HookRegistry::call('Stats::getSubmissions::queryBuilder', array($statsListQB, $contextId, $args));

		return $statsListQB;
	}

	/**
	 * Build the stats query object for getTotalStats requests
	 *
	 * @see self::getTotalStats()
	 *
	 * @return object Query object
	 */
	private function _buildGetTotalSubmissionsStatsQueryObject($contextId, $args = array()) {
		$statsListQB = new \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder($contextId);
		$statsListQB->lookingFor('totalSubmissionsStats');

		if (isset($args['timeSegment'])) {
			$statsListQB
				->timeSegment($args['timeSegment'])
				->orderColumn($args['timeSegment'])
				->orderDirection('DESC');
		}

		// filter by (OJS) section i.e. (OMP) series IDs
		if (isset($args['sectionIds'])) $statsListQB->filterBySectionIds($args['sectionIds']);

		$dateStart = isset($args['dateStart']) ? $args['dateStart'] : null;
		$dateEnd = isset($args['dateEnd']) ? $args['dateEnd'] : null;
		if ($dateStart || $dateEnd) {
			$statsListQB->filterByDateRange($dateStart, $dateEnd);
		}

		\HookRegistry::call('Stats::getTotalSubmissions::queryBuilder', array($statsListQB, $contextId, $args));

		return $statsListQB;
	}

	/**
	 * Build the stats query object for getProperties of a Submission requests
	 *
	 * @see self::getProperties()
	 *
	 * @return object Query object
	 */
	private function _buildGetSubmissionQueryObject($contextId, $submissionId, $args = array()) {
		$statsListQB = new \PKP\Services\QueryBuilders\PKPStatsListQueryBuilder($contextId);
		$statsListQB
			->lookingFor('submissionStats')
			->filterBySubmissionIds($submissionId);

		if (isset($args['timeSegment'])) {
			$statsListQB
				->timeSegment($args['timeSegment'])
				->orderColumn($args['timeSegment'])
				->orderDirection('DESC');
		}

		$dateStart = isset($args['dateStart']) ? $args['dateStart'] : null;
		$dateEnd = isset($args['dateEnd']) ? $args['dateEnd'] : null;
		if ($dateStart || $dateEnd) {
			$statsListQB->filterByDateRange($dateStart, $dateEnd);
		}

		\HookRegistry::call('Stats::getSubmission::queryBuilder', array($statsListQB, $contextId, $submissionId, $args));

		return $statsListQB;
	}

	/**
	 * Filter submissions by the given search phrase
	 *
	 * @param $searchPhrase string
	 * @return array of submission IDs
	 */
	private function _filterSubmissionsBySearchPhrase($contextId, $searchPhrase) {
		$submissionIds = array();
		$submissionService = \ServicesContainer::instance()->get('submission');
		$submissions = $submissionService->getSubmissions($contextId, array('searchPhrase' => $searchPhrase));
		if (!empty($submissions)) {
			$submissionIds = array_map(
				function($submission){
					return $submission->getId();
				},
				$submissions
			);
		}
		return $submissionIds;
	}

	/**
	 * Get all time segments (months or days) between the given start and the end date.
	 * It is assumed that startDate <= endDate
	 *
	 * @param $startDate string
	 * @param $endDate string
	 * @param $timeSegment string
	 * @return array of time segments in ASC order
	 */
	private function _getTimeSegments($startDate, $endDate, $timeSegment) {
		if ($timeSegment == STATISTICS_DIMENSION_MONTH) {
			$resultDateFormat = 'Ym';
			$interval = '+1 month';
		} elseif ($timeSegment == STATISTICS_DIMENSION_DAY) {
			$resultDateFormat = 'Ymd';
			$interval = '+1 day';
		}
		$startTime  = strtotime($startDate);
		$endTime  = strtotime($endDate);

		$timeSegments = array();
		while($startTime <= $endTime) {
			$timeSegments[] = date($resultDateFormat, $startTime);
			$startTime = strtotime(date('Ymd', $startTime) . $interval);
		}
		return $timeSegments;
	}
}
