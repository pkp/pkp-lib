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
		$statsListQB = $this->_buildGetOrderedSubmissionsQueryObject($contextId, $args);
		$statsQO = $statsListQB->get();
		/* default: SELECT submission_id, SUM(metric) AS metric FROM metrics WHERE context_id = ? AND assoc_type IN (1048585, 515) AND metric_type = 'ojs::counter' GROUP BY submission_id  ORDER BY metric DESC */
		/* with all filters: SELECT submission_id, SUM(metric) AS metric FROM metrics WHERE context_id = ? AND assoc_type IN (1048585, 515) AND metric_type = 'ojs::counter' AND pkp_section_id = ? AND day BETWEEN ? AND ? AND submission_id = ? GROUP BY submission_id  ORDER BY metric DESC */

		if ($statsQO) {
			$dao = \DAORegistry::getDAO('MetricsDAO');
			$result = $dao->retrieve($statsQO->toSql(), $statsQO->getBindings());
			$records = $result->GetAll();
		}
		return $records;
	}

	/**
	 * Get statistics records of the submissions (filtered by request parameters),
	 * containing given time segments, abstractViews and totalFileViews,
	 * ordered DESC by the time segments.
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
		$statsListQB = $this->_buildGetTotalSubmissionsStatsQueryObject($contextId, $args);
		$statsQO = $statsListQB->get();
		/* default monthly: SELECT month, assoc_type, SUM(metric) AS metric FROM metrics WHERE context_id = ? AND assoc_type IN (1048585, 515) AND metric_type = 'ojs::counter' GROUP BY month, assoc_type ORDER BY month DESC */
		/* with filters monthly: SELECT month, assoc_type, SUM(metric) AS metric FROM metrics WHERE context_id = ? AND assoc_type IN (1048585, 515) AND metric_type = 'ojs::counter' AND pkp_section_id = ? AND day BETWEEN ? AND ? AND submission_id = ? GROUP BY month, assoc_type ORDER BY month DESC */

		if ($statsQO) {
			$dao = \DAORegistry::getDAO('MetricsDAO');
			$result = $dao->retrieve($statsQO->toSql(), $statsQO->getBindings());
			$records = $result->GetAll();
		}
		return $records;
	}

	/**
	 * @see \PKP\Services\EntityProperties\EntityPropertyInterface::getProperties()
	 *
	 * @param $entity Submission
	 * @param $props array
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 *
	 * @return array
	 */
	public function getProperties($entity, $props, $args = null) {
		$entityService = null;
		if (is_a($entity, 'Submission')) {
			$entityService = \ServicesContainer::instance()->get('submission');
			$params = array(
				'entityAssocType' => ASSOC_TYPE_SUBMISSION,
				'fileAssocType' => ASSOC_TYPE_SUBMISSION_FILE,
			);
			$statsListQB = $this->_buildGetSubmissionQueryObject($entity->getContextId(), $entity->getId(), $args['params']);
			$statsQO = $statsListQB->get();
			/* default monthly: SELECT month, assoc_type, file_type, SUM(metric) AS metric FROM metrics WHERE submission_id = ? AND assoc_type IN (1048585, 515) AND metric_type = 'ojs::counter' GROUP BY month, assoc_type, file_type, month */
			/* with filters monthly: SELECT month, assoc_type, file_type, SUM(metric) AS metric FROM metrics WHERE submission_id = ? AND assoc_type IN (1048585, 515) AND metric_type = 'ojs::counter' AND day BETWEEN ? AND ? GROUP BY month, assoc_type, file_type, month */
		}

		$dao = \DAORegistry::getDAO('MetricsDAO');
		$result = $dao->retrieve($statsQO->toSql(), $statsQO->getBindings());
		$records = $result->GetAll();

		$values = $this->getRecordProperties($records, $params, $props, $args);

		if ($entityService) {
			$values['object'] = $entityService->getSummaryProperties($entity, $args);
		}

		\HookRegistry::call('Stats::getProperties::values', array(&$values, $entity, $props, $args));

		return $values;
	}

	/**
	 * @see \PKP\Services\EntityProperties\EntityPropertyInterface::getSummaryProperties()
	 *
	 * @param $entity Submission
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 *
	 * @return array
	 */
	public function getSummaryProperties($entity, $args = null) {
		$props = array (
			'total', 'abstractViews', 'totalFileViews', 'pdf', 'html', 'other', 'timeSegments',
		);

		\HookRegistry::call('Stats::getProperties::summaryProperties', array(&$props, $entity, $args));

		return $this->getProperties($entity, $props, $args);
	}

	/**
	 * @see \PKP\Services\EntityProperties\EntityPropertyInterface::getFullProperties()
	 * @param $entity Submission
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 * @return array
	 */
	public function getFullProperties($entity, $args = null) {
		$props = array (
			'total', 'abstractViews', 'totalFileViews', 'pdf', 'html', 'other', 'timeSegments',
		);

		\HookRegistry::call('Stats::getProperties::fullProperties', array(&$props, $entity, $args));

		return $this->getProperties($entity, $props, $args);
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
	 * 		@otion int fileAssocType
	 * @param $props array
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 *
	 * @return array
	 */
	public function getTimeSegments($records, $params, $props, $args = null) {
		// get entity (Submission) and file assoc type
		// used for abstract and file views
		$entityAssocType = $params['entityAssocType'];
		$fileAssocType = $params['fileAssocType'];
		// get the requested and used time segment (month or day)
		if (isset($args['params']['timeSegment'])) {
			$timeSegment = $args['params']['timeSegment'] == 'day' ? STATISTICS_DIMENSION_DAY : STATISTICS_DIMENSION_MONTH;
		} else {
			$timeSegment = STATISTICS_DIMENSION_MONTH;
		}

		// get all existing dates (monts or days)
		$timeSegmentDates = array();
		foreach ($records as $record) {
			if (!in_array($record[$timeSegment], $timeSegmentDates)) $timeSegmentDates[] = $record[$timeSegment];
		}

		// Get stats for all existing days (months or days), and
		// prepare the timeSegments return values (date, dateLabel and the actual stats for each time segment)
		$allTimeSegments = $timeSegmentsStats = array();
		foreach ($timeSegmentDates as $timeSegmentDate) {
			// all stats we are interested in
			$total = $abstractViews = $fileViews = $pdfs = $htmls = $others = 0;

			// get all records with the current date
			$dateRecords = array_filter($records, function ($record) use ($timeSegmentDate, $timeSegment) {
				return ($record[$timeSegment] == $timeSegmentDate);
			});

			// get total stats for the current date
			if (in_array('total', $props)) {
				// calculate the total = sum of all for the current date
				$total = array_sum(array_map(
					function($record){
						return $record[STATISTICS_METRIC];
					},
					$dateRecords
				));
				$timeSegmentsStats[$timeSegmentDate]['total'] = $total;
			}

			// get abstract views for the current time segment date
			if (in_array('abstractViews', $props)) {
				// get abstract views records (containing the entity (Submisison) assoc type) for the current date
				$abstractViewsDateRecords = array_filter($dateRecords, function ($record) use ($entityAssocType) {
					return ($record[STATISTICS_DIMENSION_ASSOC_TYPE] == $entityAssocType);
				});
				// calculate the abstract views = sum of all abstract views for the current date
				$abstractViews = array_sum(array_map(
					function($record){
						return $record[STATISTICS_METRIC];
					},
					$abstractViewsDateRecords
				));
				$timeSegmentsStats[$timeSegmentDate]['abstractViews'] = $abstractViews;
			}

			// get file views for the current date
			if (in_array('totalFileViews', $props)) {
				// get file views records (containing the file assoc type) for the current date
				$fileViewsDateRecords = array_filter($dateRecords, function ($record) use ($fileAssocType) {
					return ($record[STATISTICS_DIMENSION_ASSOC_TYPE] == $fileAssocType);
				});
				// calculate the total file views = sum of all file views for the current date
				$totalFileViews = array_sum(array_map(
					function($record){
						return $record[STATISTICS_METRIC];
					},
					$fileViewsDateRecords
				));
				$timeSegmentsStats[$timeSegmentDate]['totalFileViews'] = $totalFileViews;
			}

			// get pdf views for the current date
			if (in_array('pdf', $props)) {
				// get pdf views records (containing the file assoc type and the pdf file type) for the current date
				assert(array_key_exists(STATISTICS_DIMENSION_FILE_TYPE, $record));
				$pdfDateRecords = array_filter($dateRecords, function ($record) use ($fileAssocType) {
					return ($record[STATISTICS_DIMENSION_ASSOC_TYPE] == $fileAssocType && $record[STATISTICS_DIMENSION_FILE_TYPE] == STATISTICS_FILE_TYPE_PDF);
				});
				// calculate the pdf views = sum of all pdf views for the current date
				$pdfs = array_sum(array_map(
					function($record){
						return $record[STATISTICS_METRIC];
					},
					$pdfDateRecords
				));
				$timeSegmentsStats[$timeSegmentDate]['pdf'] = $pdfs;
			}

			// get html views for the current date
			if (in_array('html', $props)) {
				// get html views records (containing the file assoc type and the html file type) for the current date
				assert(array_key_exists(STATISTICS_DIMENSION_FILE_TYPE, $record));
				$htmlDateRecords = array_filter($dateRecords, function ($record) use ($fileAssocType) {
					return ($record[STATISTICS_DIMENSION_ASSOC_TYPE] == $fileAssocType && $record[STATISTICS_DIMENSION_FILE_TYPE] == STATISTICS_FILE_TYPE_HTML);
				});
				// calculate the html views = sum of all html views for the current date
				$htmls = array_sum(array_map(
					function($record){
						return $record[STATISTICS_METRIC];
					},
					$htmlDateRecords
				));
				$timeSegmentsStats[$timeSegmentDate]['html'] = $htmls;
			}

			// get other file type views for the current date
			if (in_array('other', $props)) {
				// get other file tpye views records (containing the file assoc type and the other file type) for the current date
				assert(array_key_exists(STATISTICS_DIMENSION_FILE_TYPE, $record));
				$otherDateRecords = array_filter($dateRecords, function ($record) use ($fileAssocType) {
					return ($record[STATISTICS_DIMENSION_ASSOC_TYPE] == $fileAssocType && $record[STATISTICS_DIMENSION_FILE_TYPE] == STATISTICS_FILE_TYPE_OTHER);
				});
				// calculate the other file type views = sum of all other file type views for the current date
				$others = array_sum(array_map(
					function($record){
						return $record[STATISTICS_METRIC];
					},
					$otherDateRecords
				));
				$timeSegmentsStats[$timeSegmentDate]['other'] = $others;
			}

			// get time segment date label
			if ($timeSegment == STATISTICS_DIMENSION_MONTH) {
				$dateLabel = strftime('%B %Y', strtotime($timeSegmentDate.'01'));
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
	 * 		@otion int fileAssocType
	 * @param $props array
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 *		$args['params] array of validated request parameters
	 *
	 * @return array
	 */
	public function getRecordProperties($records, $params, $props, $args = null) {
		$values = array();
		// get entity (Submission) and file assoc type
		// used for abstract and file views
		$entityAssocType = $params['entityAssocType'];
		$fileAssocType = $params['fileAssocType'];
		foreach ($props as $prop) {
			switch ($prop) {
				case 'timeSegments':
					$values['timeSegments'] = $this->getTimeSegments($records, $params, $props, $args);
					break;
				case 'total':
					// total = sum of all records
					$values[$prop] = array_sum(array_map(
						function($record){
							return $record[STATISTICS_METRIC];
						},
						$records
					));
					break;
				case 'abstractViews':
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
					break;
				case 'totalFileViews':
					// filter file views records (containing the file assoc type)
					$fileViewsRecords = array_filter($records, function ($record) use ($fileAssocType) {
						return ($record[STATISTICS_DIMENSION_ASSOC_TYPE] == $fileAssocType);
					});
					// total file views = sum of all ttotal file views records
					$values[$prop] = array_sum(array_map(
						function($record){
							return $record[STATISTICS_METRIC];
						},
						$fileViewsRecords
					));
					break;
				case 'pdf':
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
					break;
				case 'html':
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
					break;
				case 'other':
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
			->orderBy($args['orderBy'], $args['orderDirection']);

		if (isset($args['sectionIds'])) $statsListQB->filterBySectionIds($args['sectionIds']);

		$dateStart = isset($args['dateStart']) ? $args['dateStart'] : null;
		$dateEnd = isset($args['dateEnd']) ? $args['dateEnd'] : null;
		if ($dateStart || $dateEnd) {
			$statsListQB->filterByDateRange($dateStart, $dateEnd);
		}

		if (isset($args['searchPhrase'])) $statsListQB->filterBySearchPhrase($args['searchPhrase']);

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
				->orderBy($args['timeSegment'], STATISTICS_ORDER_DESC);
		}

		if (isset($args['sectionIds'])) $statsListQB->filterBySectionIds($args['sectionIds']);

		$dateStart = isset($args['dateStart']) ? $args['dateStart'] : null;
		$dateEnd = isset($args['dateEnd']) ? $args['dateEnd'] : null;
		if ($dateStart || $dateEnd) {
			$statsListQB->filterByDateRange($dateStart, $dateEnd);
		}

		if (isset($args['searchPhrase'])) $statsListQB->filterBySearchPhrase($args['searchPhrase']);

		\HookRegistry::call('Stats::getSubmissions::queryBuilder', array($statsListQB, $contextId, $args));

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
				->orderBy($args['timeSegment'], STATISTICS_ORDER_DESC);
		}

		$dateStart = isset($args['dateStart']) ? $args['dateStart'] : null;
		$dateEnd = isset($args['dateEnd']) ? $args['dateEnd'] : null;
		if ($dateStart || $dateEnd) {
			$statsListQB->filterByDateRange($dateStart, $dateEnd);
		}

		\HookRegistry::call('Stats::getSubmission::queryBuilder', array($statsListQB, $contextId, $submissionId, $args));

		return $statsListQB;
	}

}
