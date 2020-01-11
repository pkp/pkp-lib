<?php

/**
 * @file classes/services/QueryBuilders/PKPStatsEditorialQueryBuilder.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsEditorialQueryBuilder
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch stats records from the
 *  metrics table.
 */

namespace PKP\Services\QueryBuilders;

use PKP\Services\QueryBuilders\BaseQueryBuilder;
use Illuminate\Database\Capsule\Manager as Capsule;

abstract class PKPStatsEditorialQueryBuilder extends BaseQueryBuilder {

	/** @var array Return stats for activity in these contexts */
	protected $contextIds = [];

	/** @var string Return stats for activity before this date */
	protected $dateEnd;

	/** @var string Return stats for activity after this date */
	protected $dateStart;

	/** @var array Return stats for activity in these sections (series in OMP) */
	protected $sectionIds = [];

	/** @var string The table column name for section IDs (OJS) or series IDs (OMP) */
	public $sectionIdsColumn;

	/**
	 * Set the contexts to return activity for
	 *
	 * @param array|int $contextIds
	 * @return \PKP\Services\QueryBuilders\PKPStatsEditorialQueryBuilder
	 */
	public function filterByContexts($contextIds) {
		$this->contextIds = is_array($contextIds) ? $contextIds : [$contextIds];
		return $this;
	}

	/**
	 * Set the section ids to include activity for. This is stored under
	 * the section_id db column but in OMP refers to seriesIds.
	 *
	 * @param array|int $sectionIds
	 * @return \PKP\Services\QueryBuilders\PKPStatsEditorialQueryBuilder
	 */
	public function filterBySections($sectionIds) {
		$this->sectionIds = is_array($sectionIds) ? $sectionIds : [$sectionIds];
		return $this;
	}

	/**
	 * Set the date to get activity before
	 *
	 * @param string $dateEnd YYYY-MM-DD
	 * @return \PKP\Services\QueryBuilders\PKPStatsEditorialQueryBuilder
	 */
	public function before($dateEnd) {
		$this->dateEnd = $dateEnd;
		return $this;
	}

	/**
	 * Set the date to get activity after
	 *
	 * @param string $dateStart YYYY-MM-DD
	 * @return \PKP\Services\QueryBuilders\PKPStatsEditorialQueryBuilder
	 */
	public function after($dateStart) {
		$this->dateStart = $dateStart;
		return $this;
	}

	/**
	 * Get the count of submissions received
	 *
	 * @return int
	 */
	public function countSubmissionsReceived() {
		$q = $this->_getObject();
		if ($this->dateStart) {
			$q->where('s.date_submitted', '>=', $this->dateStart);
		}
		if ($this->dateEnd) {
			$q->where('s.date_submitted', '<=', $this->dateEnd);
		}

		return $q->count();
	}

	/**
	 * Get the count of submissions that have received one or more
	 * editor decisions
	 *
	 * @param array $decisions One or more SUBMISSION_EDITOR_DECISION_*
	 * @param boolean $forSubmittedDate How date restrictions should be applied.
	 *  A false value will count the number of submissions with an editorial
	 * 	decision within the date range. A true value will count the number of
	 *  submissions received within the date range which eventually received
	 *  an editorial decision.
	 * @return int
	 */
	public function countByDecisions($decisions, $forSubmittedDate = false) {

		$q = $this->_getObject();
		$q->leftJoin('edit_decisions as ed', 's.submission_id', '=', 'ed.submission_id')
			->whereIn('ed.decision', $decisions);

		if ($forSubmittedDate) {
			if ($this->dateStart) {
				$q->where('s.date_submitted', '>=', $this->dateStart);
			}
			if ($this->dateEnd) {
				// Include date time values up to the end of the day
				$dateTime = new \DateTime($this->dateEnd);
				$dateTime->add(new \DateInterval('P1D'));
				$q->where('s.date_submitted', '<', $dateTime->format('Y-m-d'));
			}

		} else {
			if ($this->dateStart) {
				$q->where('ed.date_decided', '>=', $this->dateStart);
			}
			if ($this->dateEnd) {
				// Include date time values up to the end of the day
				$dateTime = new \DateTime($this->dateEnd);
				$dateTime->add(new \DateInterval('P1D'));
				$q->where('ed.date_decided', '<', $dateTime->format('Y-m-d'));
			}
		}

		// Ensure that the decisions being counted have not been
		// reversed. For example, a submission may have been accepted
		// and then later declined. We check the current status to
		// exclude submissions where the status doesn't match the
		// decisions we are looking for.
		$declineDecisions = [SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE];
		if (count(array_intersect($declineDecisions, $decisions))) {
			$q->where('s.status', '=', STATUS_DECLINED);
		} else {
			$q->where('s.status', '!=', STATUS_DECLINED);
		}

		$q->select(Capsule::raw('COUNT(DISTINCT s.submission_id) as count'));

		return $q->get()->first()->count;
	}

	/**
	 * Get the count of submissions by one or more status
	 *
	 * @param int|array $status One or more of STATUS_*
	 */
	public function countByStatus($status) {
		return $this->_getObject()
			->whereIn('s.status', (array) $status)
			->count();
	}

	/**
	 * Get the count of active submissions by one or more stages
	 *
	 * @param array $stages One or more of WORKFLOW_STAGE_ID_*
	 */
	public function countActiveByStages($stages) {
		import('lib.pkp.classes.submission.PKPSubmission');

		return $this->_getObject()
			->where('s.status', '=', STATUS_QUEUED)
			->whereIn('s.stage_id', $stages)
			->count();
	}

	/**
	 * Get the count of published submissions
	 */
	public function countPublished() {
		import('lib.pkp.classes.submission.PKPSubmission');

		$q = $this->_getObject()
			->where('s.status', '=', STATUS_PUBLISHED);

		// Only match against the publication date of a
		// submission's first published publication so
		// that updated versions are excluded.
		if ($this->dateStart || $this->dateEnd) {
			$q->leftJoin('publications as p', function($q) {
				$q->where('p.publication_id', function($q) {
					$q->from('publications as p2')
						->where('p2.submission_id', '=', Capsule::raw('s.submission_id'))
						->where('p2.status', '=', STATUS_PUBLISHED)
						->orderBy('p2.date_published', 'ASC')
						->limit(1)
						->select('p2.publication_id');
				});
			});
			if ($this->dateStart) {
				$q->where('p.date_published', '>=', $this->dateStart);
			}
			if ($this->dateEnd) {
				$q->where('p.date_published', '<=', $this->dateEnd);
			}
		}

		return $q->count();
	}

	/**
	 * Get the number of days to reach a particular editor decision
	 *
	 * This list includes any completed submission which has received
	 * one of the editor decisions.
	 *
	 * @param array $decisions One or more SUBMISSION_EDITOR_DECISION_*
	 * @return array Days between submission and the first decision in
	 *   the list of requested submissions
	 */
	public function getDaysToDecisions($decisions) {
		$q = $this->_getDaysToDecisionsObject($decisions);
		$dateDiff = $this->_dateDiff('ed.date_decided', 's.date_submitted');
		$q->select(Capsule::raw($dateDiff . ' as time'));
		return $q->pluck('time')->toArray();
	}

	/**
	 * Get the average number of days to reach a particular
	 * editor decision
	 *
	 * This average includes any completed submission which has received
	 * one of the editor decisions.
	 *
	 * @param array $decisions One or more SUBMISSION_EDITOR_DECISION_*
	 * @return float Average days between submission and the first decision
	 * 		in the list of requested submissions
	 */
	public function getAverageDaysToDecisions($decisions) {
		$q = $this->_getDaysToDecisionsObject($decisions);
		$dateDiff = $this->_dateDiff('ed.date_decided', 's.date_submitted');
		$q->select(Capsule::raw('AVG(' . $dateDiff . ') as average'));
		return $q->get()->first()->average;
	}

	/**
	 * Generate a query object based on the configured conditions.
	 *
	 * The dateStart and dateEnd filters are not handled here because
	 * the dates must be applied differently for each set of data.
	 *
	 * @return QueryObject
	 */
	protected function _getObject() {
		$q = Capsule::table('submissions as s');
		if (!empty($this->contextIds)) {
			$q->whereIn('s.context_id', $this->contextIds);
		}
		if (!empty($this->sectionIds)) {
			$q->leftJoin('publications as ps', 's.current_publication_id', '=', 'ps.publication_id')
				->whereIn('ps.' . $this->sectionIdsColumn, $this->sectionIds)
				->whereNotNull('ps.publication_id');
		}

		\HookRegistry::call('Stats::editorial::queryObject', array($q, $this));

		return $q;
	}

	/**
	 * Generate a query object to get a submission's first
	 * decision of the requested decision types
	 *
	 * Pass an empty $decisions array to return the number of days to
	 * _any_ decision.
	 *
	 * @param array $decisions One or more SUBMISSION_EDITOR_DECISION_*
	 * @return QueryObject
	 */
	protected function _getDaysToDecisionsObject($decisions) {

		$q = $this->_getObject();

		$q->leftJoin('edit_decisions as ed', function($q) use ($decisions) {
			$q->where('ed.edit_decision_id', function($q) use ($decisions) {
				$q->from('edit_decisions as ed2')
					->where('ed2.submission_id', '=', Capsule::raw('s.submission_id'));
				if (!empty($decisions)) {
					$q->whereIn('ed2.decision', $decisions);
				}
				$q->orderBy('ed2.date_decided', 'ASC')
					->limit(1)
					->select('ed2.edit_decision_id');
			});
		});

		$q->whereNotNull('ed.submission_id')
			->whereNotNull('s.date_submitted');

		if ($this->dateStart) {
			$q->where('s.date_submitted', '>=', $this->dateStart);
		}
		if ($this->dateEnd) {
			// Include date time values up to the end of the day
			$dateTime = new \DateTime($this->dateEnd);
			$dateTime->add(new \DateInterval('P1D'));
			$q->where('s.date_submitted', '<=', $dateTime->format('Y-m-d'));
		}

		return $q;
	}

	/**
	 * Retrieves a suitable diff by days clause according to the active database driver
	 * @param string $leftDate
	 * @param string $rightDate
	 * @return string
	 */
	private function _dateDiff(string $leftDate, string $rightDate)	{
		switch (\Config::getVar('database', 'driver')) {
			case 'mysql':
			case 'mysqli':
				return 'DATEDIFF(' . $leftDate . ',' . $rightDate . ')';
		}
		return "DATE_PART('day', " . $leftDate . " - " . $rightDate . ")";
	}
}

