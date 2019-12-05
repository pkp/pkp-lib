<?php
/**
 * @file classes/services/QueryBuilders/SubmissionStatisticsQueryBuilder.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionStatisticsQueryBuilder
 * @ingroup query_builders
 *
 * @brief Submission statistics query builder
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;

class SubmissionStatisticsQueryBuilder extends BaseQueryBuilder {
	/** @var int context ID */
	private $_contextId = null;

	/** @var iterable List of section ids */
	private $_sectionIds = null;

	/** @var DateTimeInterface Start date */
	private $_start = null;

	/** @var DateTimeInterface End date */
	private $_end = null;

	/**
	 * Filter the statistics by context
	 * @param $id int Context ID
	 * @return \PKP\Services\QueryBuilders\SubmissionStatisticsQueryBuilder
	 */
	public function withContext(?int $id) : self
	{
		$this->_contextId = $id;
		return $this;
	}

	/**
	 * Filter the statistics by section
	 * @param $ids iterable
	 * @return \PKP\Services\QueryBuilders\SubmissionStatisticsQueryBuilder
	 */
	public function withSections(?iterable $ids) : self
	{
		$this->_sectionIds = $ids;
		return $this;
	}

	/**
	 * Filter the statistics by date range
	 * @param $start DateTimeInterface
	 * @param $end DateTimeInterface
	 * @return \PKP\Services\QueryBuilders\SubmissionStatisticsQueryBuilder
	 */
	public function withDateRange(?\DateTimeInterface $start, ?\DateTimeInterface $end) : self
	{
		$this->_start = $start;
		$this->_end = $end;
		return $this;
	}

	/**
	 * Build the query
	 * @return Illuminate\Support\Collection Query object
	 */
	public function build() : \Illuminate\Database\Query\Builder
	{
		// Access submission constants.
		import('lib.pkp.classes.submission.Submission');

		// Access decision actions constants.
		import('classes.workflow.EditorDecisionActionsManager');

		$capsule = $this->capsule;

		$publishedSubmissionsQuery = $capsule
			->table('published_submissions AS ps')
			->where('ps.submission_id', Capsule::raw('ranged_s.submission_id'))
			->whereRaw(
				'ps.date_published BETWEEN COALESCE(?, ps.date_published) AND COALESCE(?, ps.date_published)',
				[$this->_start, $this->_end]
			)
			->select(Capsule::raw(0));

		// Query to extract statistics from the submissions
		$statisticsQuery = $capsule
			->table('submissions AS s')
			// Match active submissions
			->leftJoin('submissions AS active_s', function ($join) {
				$join
					->on('active_s.submission_id', 's.submission_id')
					->on('active_s.status', Capsule::raw(STATUS_QUEUED));
			})
			// Match submissions in the given date range
			->leftJoin('submissions AS ranged_s', function ($join) {
				$join
					->on('ranged_s.submission_id', 's.submission_id')
					->whereRaw(
						'ranged_s.date_submitted BETWEEN COALESCE(?, ranged_s.date_submitted) AND COALESCE(?, ranged_s.date_submitted)',
						[$this->_start, $this->_end]
					);
			})
			// Match the latest decision
			->leftJoin('edit_decisions AS ed', function ($join) {
				$join->where('ed.edit_decision_id', function ($query) {
					$query
						->from('edit_decisions AS ed')
						->where('ed.submission_id', Capsule::raw('s.submission_id'))
						->orderBy('ed.stage_id', 'DESC')
						->orderBy('ed.round', 'DESC')
						->orderBy('ed.date_decided', 'DESC')
						->limit(1)
						->select('ed.edit_decision_id');
				});
			})
			// Match the latest decision of the ranged submissions
			->leftJoin('edit_decisions AS ranged_s_ed', function ($join) {
				$join->where('ranged_s_ed.edit_decision_id', function ($query) {
					$query
						->from('edit_decisions AS ed')
						->where('ed.submission_id', Capsule::raw('ranged_s.submission_id'))
						->orderBy('ed.stage_id', 'DESC')
						->orderBy('ed.round', 'DESC')
						->orderBy('ed.date_decided', 'DESC')
						->limit(1)
						->select('ed.edit_decision_id');
				});
			})
			// Match the latest decision only if it's in the given date range
			->leftJoin('edit_decisions AS ranged_ed', function ($join) {
				$join
					->on('ranged_ed.edit_decision_id', 'ed.edit_decision_id')
					->whereRaw(
						'ranged_ed.date_decided BETWEEN COALESCE(?, ranged_ed.date_decided) AND COALESCE(?, ranged_ed.date_decided)',
						[$this->_start, $this->_end]
					);
			})
			// Match the first decision
			->leftJoin('edit_decisions AS first_ed', function ($join) {
				$join
					->whereNotNull('ranged_ed.edit_decision_id')
					->where('first_ed.edit_decision_id', function ($query) {
						$query
							->from('edit_decisions AS ed')
							->where('ed.submission_id', Capsule::raw('s.submission_id'))
							->orderBy('ed.stage_id', 'ASC')
							->orderBy('ed.round', 'ASC')
							->orderBy('ed.date_decided', 'ASC')
							->limit(1)
							->select('ed.edit_decision_id');
					});
			})
			->select([
				// Statistics for the active submissions
				Capsule::raw('COUNT(active_s.submission_id) AS active_total'),
				Capsule::raw('COUNT(CASE WHEN active_s.stage_id = ' . WORKFLOW_STAGE_ID_SUBMISSION . ' THEN 0 END) AS active_submission'),
				Capsule::raw('COUNT(CASE WHEN active_s.stage_id = ' . WORKFLOW_STAGE_ID_INTERNAL_REVIEW . ' THEN 0 END) AS active_internal_review'),
				Capsule::raw('COUNT(CASE WHEN active_s.stage_id = ' . WORKFLOW_STAGE_ID_EXTERNAL_REVIEW . ' THEN 0 END) AS active_external_review'),
				Capsule::raw('COUNT(CASE WHEN active_s.stage_id = ' . WORKFLOW_STAGE_ID_EDITING . ' THEN 0 END) AS active_copy_editing'),
				Capsule::raw('COUNT(CASE WHEN active_s.stage_id = ' . WORKFLOW_STAGE_ID_PRODUCTION . ' THEN 0 END) AS active_production'),

				// Statistics for all submissions
				Capsule::raw('COUNT(ranged_s.submission_id) AS submission_received'),
				Capsule::raw('COUNT(CASE WHEN ranged_ed.decision IN (' . SUBMISSION_EDITOR_DECISION_ACCEPT . ', ' . SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION . ') THEN 0 END) AS submission_accepted'),
				Capsule::raw('COUNT(CASE WHEN s.status = ' . STATUS_PUBLISHED . ' AND EXISTS ('
					. $publishedSubmissionsQuery->toSql() . '
				) THEN 0 END) AS submission_published'),
				Capsule::raw('COUNT(CASE WHEN ranged_ed.decision = ' . SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE . ' THEN 0 END) AS submission_declined_initial'),
				Capsule::raw('COUNT(CASE WHEN ranged_ed.decision = ' . SUBMISSION_EDITOR_DECISION_DECLINE . ' THEN 0 END) AS submission_declined'),
				Capsule::raw('COUNT(
					CASE WHEN ranged_s.status = ' . STATUS_DECLINED . '
						AND (ed.decision NOT IN (' . SUBMISSION_EDITOR_DECISION_DECLINE . ', ' . SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE . ')
							OR ed.decision IS NULL)
						THEN 0
					END
				) AS submission_declined_other'),

				// Average days to decide
				Capsule::raw('AVG(
					CASE WHEN ranged_ed.decision IN (' . SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION . ', ' . SUBMISSION_EDITOR_DECISION_ACCEPT . ')
						THEN ' . $this->_dateDiff('ranged_ed.date_decided', 'COALESCE(s.date_submitted, s.last_modified)') . '
					END
				) AS submission_days_to_accept'),
				Capsule::raw('AVG(
					CASE WHEN ranged_ed.decision IN (' . SUBMISSION_EDITOR_DECISION_DECLINE . ', ' . SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE . ')
						THEN ' . $this->_dateDiff('ranged_ed.date_decided', 'COALESCE(s.date_submitted, s.last_modified)') . '
					END
				) AS submission_days_to_reject'),
				Capsule::raw('AVG(' . $this->_dateDiff('first_ed.date_decided', 'COALESCE(s.date_submitted, s.last_modified)') . ') AS submission_days_to_first_decision'),
				Capsule::raw('AVG(' . $this->_dateDiff('ranged_ed.date_decided', 'COALESCE(s.date_submitted, s.last_modified)') . ') AS submission_days_to_decide'),

				// Acceptance and rejection rate
				Capsule::raw('COUNT(CASE WHEN ranged_s_ed.decision IN (' . SUBMISSION_EDITOR_DECISION_ACCEPT . ', ' . SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION . ') THEN 0 END) / COUNT(ranged_s.submission_id) * 100 AS submission_acceptance_rate'),
				Capsule::raw('COUNT(CASE WHEN ranged_s_ed.decision = ' . SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE . ' THEN 0 END) / COUNT(ranged_s.submission_id) * 100 AS submission_declined_initial_rate'),
				Capsule::raw('COUNT(CASE WHEN ranged_s_ed.decision = ' . SUBMISSION_EDITOR_DECISION_DECLINE . ' THEN 0 END) / COUNT(ranged_s.submission_id) * 100 AS submission_declined_rate'),
				Capsule::raw('COUNT(
					CASE WHEN ranged_s.status = ' . STATUS_DECLINED . '
						AND (
							ranged_s_ed.decision NOT IN (' . SUBMISSION_EDITOR_DECISION_DECLINE . ', ' . SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE . ')
							OR ranged_s_ed.decision IS NULL
						)
						THEN 0
					END
				) / COUNT(ranged_s.submission_id) * 100 AS submission_declined_other_rate')
			])
			// Skip unfinished submissions
			->where('s.submission_progress', Capsule::raw('0'));

		// Add filter by context
		if ($this->_contextId) {
			$statisticsQuery->where('s.context_id', $this->_contextId);
		}

		// Add filter by sections
		if ($this->_sectionIds && count($this->_sectionIds)) {
			$statisticsQuery->whereIn('s.section_id', $this->_sectionIds);
		}

		// Extract the amount of years covered in the dataset
		$yearsQuery = $capsule->getConnection()->query()->selectRaw('
			EXTRACT(YEAR FROM COALESCE(?, CURRENT_TIMESTAMP))
			- EXTRACT(YEAR FROM
				COALESCE(
					?,
					('
						. $capsule
							->table('submissions AS s')
							->whereNotNull('s.date_submitted')
							->orderBy('s.date_submitted')
							->limit(1)
							->select('s.date_submitted')
							->toSql()
					. '),
					CURRENT_TIMESTAMP
				)
			) + 1 AS count',
			[$this->_start, $this->_end]
		);

		// Final query
		$query = $capsule
			->table(Capsule::raw('(' . $statisticsQuery->toSql() . ') AS statistics'))
			->join(Capsule::raw('(' . $yearsQuery->toSql() . ') AS years'), Capsule::raw('1'), Capsule::raw('1'))
			->select([
				Capsule::raw('statistics.*'),
				Capsule::raw('COALESCE(statistics.submission_declined_initial, 0) + COALESCE(statistics.submission_declined, 0) + COALESCE(statistics.submission_declined_other, 0) AS submission_declined_total'),
				Capsule::raw('(COALESCE(statistics.submission_declined_initial, 0) + COALESCE(statistics.submission_declined, 0) + COALESCE(statistics.submission_declined_other, 0)) / statistics.submission_received * 100 AS submission_rejection_rate'),
				// Totals averaged by year
				Capsule::raw('statistics.submission_received / years.count AS avg_submission_received'),
				Capsule::raw('statistics.submission_accepted / years.count AS avg_submission_accepted'),
				Capsule::raw('statistics.submission_published / years.count AS avg_submission_published'),
				Capsule::raw('statistics.submission_declined_initial / years.count AS avg_submission_declined_initial'),
				Capsule::raw('statistics.submission_declined / years.count AS avg_submission_declined'),
				Capsule::raw('statistics.submission_declined_other / years.count AS avg_submission_declined_other'),
				Capsule::raw('(COALESCE(statistics.submission_declined_initial, 0) + COALESCE(statistics.submission_declined, 0) + COALESCE(statistics.submission_declined_other, 0)) / years.count AS avg_submission_declined_total')
			]);

		return self::addBindings($query, $publishedSubmissionsQuery, $statisticsQuery, $yearsQuery);
	}

	/**
	 * Retrieves a suitable diff by days clause according to the active database driver
	 * @param $leftDate string
	 * @param $rightDate string
	 * @return string
	 */
	private function _dateDiff(string $leftDate, string $rightDate) : string
	{
		switch (\Config::getVar('database', 'driver')) {
			case 'mysql':
			case 'mysqli':
				return 'DATEDIFF(' . $leftDate . ',' . $rightDate . ')';
		}
		return "DATE_PART('day', " . $leftDate . " - " . $rightDate . ")";
	}
}
