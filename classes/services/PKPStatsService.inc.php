<?php

/**
 * @file classes/services/PKPStatsService.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsService
 * @ingroup services
 *
 * @brief Helper class that encapsulates statistics business logic
 */

namespace PKP\Services;

class PKPStatsService {

	/**
	 * Get all statistics records that match the passed arguments
	 *
	 * @param array $args [
	 *  @option array contextIds Return records for these contexts
	 *  @option array submissionIds Return records for these submissions
	 *  @option array sectionIds Return records for these sections
	 *  @option string dateEnd Return records on or before this date
	 *  @option string dateStart Return records on or after this date
	 *  @option array assocTypes Return records for these types of objects. One of ASSOC_TYPE_*
	 *  @option array assocIds Return records for these objects. Only used when assocTypes is set.
	 *  @option array fileTypes Return records for these file types. One of STATISTICS_FILE_TYPE_*
	 * ]
	 * @return array
	 */
	public function getRecords($args = []) {

		// Get the stats constants
		import('classes.statistics.StatisticsHelper');

		$defaultArgs = [
			'dateStart' => STATISTICS_EARLIEST_DATE,
			'dateEnd' => date('Y-m-d', strtotime('yesterday')),

			// Require a context to be specified to prevent unwanted data leakage
			// if someone forgets to specify the context.
			'contextIds' => [CONTEXT_ID_NONE],
		];

		$args = array_merge($defaultArgs, $args);
		$statsQB = $this->getQueryBuilder($args);

		\HookRegistry::call('Stats::getRecords::queryBuilder', array($statsQB, $args));

		$statsQO = $statsQB->getRecords();

		$result = \DAORegistry::getDAO('MetricsDAO')
			->retrieve($statsQO->toSql(), $statsQO->getBindings());

		$records = [];
		while (!$result->EOF) {
			$records[] = [
				'loadId' => $result->fields['load_id'],
				'contextId' => (int) $result->fields['context_id'],
				'submissionId' => (int) $result->fields['submission_id'],
				'assocType' => (int) $result->fields['assoc_type'],
				'assocId' => (int) $result->fields['assoc_id'],
				'day' => (int) $result->fields['day'],
				'month' => (int) $result->fields['month'],
				'fileType' => (int) $result->fields['file_type'],
				'countryId' => $result->fields['country_id'],
				'region' => $result->fields['region'],
				'city' => $result->fields['city'],
				'metric' => (int) $result->fields['metric'],
				'metricType' => $result->fields['metric_type'],
				'pkpSectionId' => (int) $result->fields['pkp_section_id'],
				'assocObjectType' => (int) $result->fields['assoc_object_type'],
				'assocObjectTId' => (int) $result->fields['assoc_object_id'],
				'representation_id' => (int) $result->fields['representation_id'],
			];
			$result->MoveNext();
		}

		return $records;
	}

	/**
	 * Get the sum of a set of metrics broken down by day or month
	 *
	 * @param string $timelineInterval STATISTICS_DIMENSION_MONTH or STATISTICS_DIMENSION_DAY
	 * @param array $args Filter the records to include. See self::getRecords()
	 * @return array
	 */
	public function getTimeline($timelineInterval, $args = []) {

		$defaultArgs = [
			'dateStart' => STATISTICS_EARLIEST_DATE,
			'dateEnd' => date('Y-m-d', strtotime('yesterday')),

			// Require a context to be specified to prevent unwanted data leakage
			// if someone forgets to specify the context. If you really want to
			// get data across all contexts, pass an empty `contextId` arg.
			'contextIds' => [CONTEXT_ID_NONE],
		];

		$args = array_merge($defaultArgs, $args);
		$timelineQB = $this->getQueryBuilder($args);

		\HookRegistry::call('Stats::getTimeline::queryBuilder', array($timelineQB, $args));

		$timelineQO = $timelineQB
			->getSum([$timelineInterval])
			->orderBy($timelineInterval);

		$result = \DAORegistry::getDAO('MetricsDAO')
			->retrieve($timelineQO->toSql(), $timelineQO->getBindings());

		$dateValues = [];
		while (!$result->EOF) {
			$date = substr($result->fields[$timelineInterval], 0, 4) . '-' . substr($result->fields[$timelineInterval], 4, 2);
			if ($timelineInterval === STATISTICS_DIMENSION_DAY) {
				$date = substr($date, 0, 7) . '-' . substr($result->fields[$timelineInterval], 6, 2);
			}
			$dateValues[$date] = (int) $result->fields['metric'];
			$result->MoveNext();
		}

		$timeline = $this->getEmptyTimelineIntervals($args['dateStart'], $args['dateEnd'], $timelineInterval);

		$timeline = array_map(function($entry) use ($dateValues) {
			foreach ($dateValues as $date => $value) {
				if ($entry['date'] === $date) {
					$entry['value'] = $value;
					break;
				}
			}
			return $entry;
		}, $timeline);

		return $timeline;
	}

	/**
	 * Get a list of objects ordered by their total stats
	 *
	 * The $args argument is used to determine what records to include in
	 * the results. The $groupBy argument is used to group these records.
	 *
	 * For example, to get a list of submissions ordered by their total PDF
	 * galley views:
	 *
	 * // Get all records with the PDF file type
	 * $args = ['fileType' => STATISTICS_FILE_TYPE_PDF]
	 *
	 * // Group them by their submission ID
	 * $groupBy = STATISTICS_DIMENSION_SUBMISSION_ID
	 *
	 * @param string $groupBy The column to sum the stats by.
	 * @param string $orderDirection STATISTICS_ORDER_ASC or STATISTICS_ORDER_DESC
	 * @param array $args Filter the records to include. See self::getRecords()
	 */
	public function getOrderedObjects($groupBy, $orderDirection, $args = []) {

		$defaultArgs = [
			'dateStart' => STATISTICS_EARLIEST_DATE,
			'dateEnd' => date('Y-m-d', strtotime('yesterday')),

			// Require a context to be specified to prevent unwanted data leakage
			// if someone forgets to specify the context. If you really want to
			// get data across all contexts, pass an empty `contextId` arg.
			'contextIds' => [CONTEXT_ID_NONE],
		];

		$args = array_merge($defaultArgs, $args);
		$orderedQB = $this->getQueryBuilder($args);

		\HookRegistry::call('Stats::getOrderedObjects::queryBuilder', array($orderedQB, $args));

		$orderedQO = $orderedQB
			->getSum([$groupBy])
			->orderBy('metric', $orderDirection === STATISTICS_ORDER_ASC ? 'asc' : 'desc');

		$range = null;
		if (isset($args['count'])) {
			import('lib.pkp.classes.db.DBResultRange');
			$range = new \DBResultRange($args['count'], null, isset($args['offset']) ? $args['offset'] : 0);
		}

		$result = \DAORegistry::getDAO('MetricsDAO')
			->retrieveRange($orderedQO->toSql(), $orderedQO->getBindings(), $range);

		$objects = [];
		while (!$result->EOF) {
			$objects[] = [
				'id' => (int) $result->fields[$groupBy],
				'total' => (int) $result->fields['metric'],
			];
			$result->MoveNext();
		}

		return $objects;
	}

	/**
	 * A callback to be used with array_reduce() to add up the metric value
	 * for a record
	 *
	 * @param array $record
	 * @return integer
	 */
	public function sumMetric($total, $record) {
		$total += $record['metric'];
		return $total;
	}

	/**
	 * A callback to be used with array_filter() to return records for
	 * a file (galley, representation).
	 *
	 * @param array $record
	 * @return array
	 */
	public function filterRecordFile($record) {
		return !empty($record['fileType']);
	}

	/**
	 * A callback to be used with array_filter() to return records for
	 * a pdf file.
	 *
	 * @param array $record
	 * @return array
	 */
	public function filterRecordPdf($record) {
		return $record['fileType'] === STATISTICS_FILE_TYPE_PDF;
	}

	/**
	 * A callback to be used with array_filter() to return records for
	 * a HTML file.
	 *
	 * @param array $record
	 * @return array
	 */
	public function filterRecordHtml($record) {
		return $record['fileType'] === STATISTICS_FILE_TYPE_HTML;
	}

	/**
	 * A callback to be used with array_filter() to return records for
	 * any Other files (all files that are not PDF or HTML).
	 *
	 * @param array $record
	 * @return array
	 */
	public function filterRecordOther($record) {
		return $record['fileType'] === STATISTICS_FILE_TYPE_OTHER;
	}

	/**
	 * Get all time segments (months or days) between the start and end date
	 * with empty values.
	 *
	 * @param $startDate string
	 * @param $endDate string
	 * @param $timelineInterval string STATISTICS_DIMENSION_MONTH or STATISTICS_DIMENSION_DAY
	 * @return array of time segments in ASC order
	 */
	public function getEmptyTimelineIntervals($startDate, $endDate, $timelineInterval) {

		if ($timelineInterval === STATISTICS_DIMENSION_MONTH) {
			$dateFormat = 'Y-m';
			$labelFormat = '%B %Y';
			$interval = 'P1M';
		} elseif ($timelineInterval === STATISTICS_DIMENSION_DAY) {
			$dateFormat = 'Y-m-d';
			$labelFormat = \Config::getVar('general', 'date_format_long');
			$interval = 'P1D';
		}

		$startDate = new \DateTime($startDate);
		$endDate = new \DateTime($endDate);

		$timelineIntervals = [];
		while ($startDate->format($dateFormat) <= $endDate->format($dateFormat)) {
			$timelineIntervals[] = [
				'date' => $startDate->format($dateFormat),
				'label' => strftime($labelFormat, $startDate->getTimestamp()),
				'value' => 0,
			];
			$startDate->add(new \DateInterval($interval));
		}

		return $timelineIntervals;
	}

	/**
	 * Get a QueryBuilder object with the passed args
	 *
	 * @param array $args See self::getRecords()
	 * @return \PKP\Services\QueryBuilders\PKPStatsQueryBuilder
	 */
	protected function getQueryBuilder($args = []) {
		$statsQB = new \PKP\Services\QueryBuilders\PKPStatsQueryBuilder();
		$statsQB
			->filterByContexts($args['contextIds'])
			->before($args['dateEnd'])
			->after($args['dateStart']);

		if (!empty(($args['submissionIds']))) {
			$statsQB->filterBySubmissions($args['submissionIds']);
		}

		if (!empty($args['assocTypes'])) {
			$statsQB->filterByAssocTypes($args['assocTypes']);
			if (!empty($args['assocIds'])) {
				$statsQB->filterByAssocIds($args['assocIds']);
			}
		}

		if (!empty($args['fileTypes'])) {
			$statsQB->filterByFileTypes(($args['fileTypes']));
		}

		\HookRegistry::call('Stats::queryBuilder', array($statsQB, $args));

		return $statsQB;
	}
}