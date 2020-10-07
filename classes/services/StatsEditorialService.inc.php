<?php
/**
 * @file classes/services/StatsEditorialService.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsEditorialService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for getting
 *   editorial stats
 */
namespace APP\Services;

class StatsEditorialService extends \PKP\Services\PKPStatsEditorialService {

	/**
	 * Initialize hooks for extending PKPStatsEditorialService
	 */
	public function __construct() {
		\HookRegistry::register('EditorialStats::overview', array($this, 'modifyOverview'));
	}

	/**
	 * Collect and sanitize request params for submissions API endpoint
	 *
	 * @param $hookName string
	 * @param $args array [
	 *		@option array $overview
	 *		@option array args
	 * ]
	 *
	 * @return array
	 */
	public function modifyOverview($hookName, $args) {
		$overview =& $args[0];

		// Remove statistics not used with OPS
		$removeKeys = [];
		foreach ($overview as $key => $item) {
			if (in_array($item['key'], ['submissionsAccepted','submissionsDeclinedPostReview', 'submissionsDeclinedDeskReject', 'daysToDecision', 'daysToAccept', 'daysToReject', 'declinedDeskRate', 'declinedReviewRate'])) {
				$removeKeys[] = $key;
			}
		}

		foreach ($removeKeys as $key) {
			unset($overview[$key]);
		}
		// Reset keys
		$overview = array_values($overview);
	}

	/**
	 * Process the sectionIds param when getting the query builder
	 *
	 * @param array $args
	 */
	protected function getQueryBuilder($args = []) {
		$statsQB = parent::getQueryBuilder($args);
		if (!empty(($args['sectionIds']))) {
			$statsQB->filterBySections($args['sectionIds']);
		}
		return $statsQB;
	}
}