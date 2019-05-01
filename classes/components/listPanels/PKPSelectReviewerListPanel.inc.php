<?php
/**
 * @file components/listPanels/PKPSelectReviewerListPanel.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSelectReviewerListPanel
 * @ingroup classes_controllers_list
 *
 * @brief A class for loading a panel to select a reviewer.
 */

namespace PKP\components\listPanels;
use PKP\components\listPanels;

class PKPSelectReviewerListPanel extends ListPanel {

	/** @var array List of user IDs already assigned as a reviewer to this submission */
	public $currentlyAssigned = [];

	/** @var array List of user IDs which may not be suitable for blind review because of existing access to author details */
	public $warnOnAssignment = [];

	/**
	 * @copydoc ListPanel::set()
	 */
	public function set($args) {
		parent::set($args);
		$this->currentlyAssigned = !empty($args['currentlyAssigned']) ? $args['currentlyAssigned'] : $this->currentlyAssigned;
		$this->warnOnAssignment = !empty($args['warnOnAssignment']) ? $args['warnOnAssignment'] : $this->warnOnAssignment;
	}

	/**
	 * @copydoc ListPanel::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['selectorType'] = 'radio';
		$config['selected'] = 0;
		$config['currentlyAssigned'] = $this->currentlyAssigned;
		$config['warnOnAssignment'] = $this->warnOnAssignment;
		$config['filters'] = [
			[
				'filters' => [
					[
						'param' => 'reviewerRating',
						'title' => __('reviewer.list.filterRating'),
						'value' => 3,
						'min' => 1,
						'max' => 5,
						'useStars' => true,
						'starLabel' => __('reviewer.list.reviewerRating'),
					],
					[
						'param' => 'reviewsCompleted',
						'title' => __('reviewer.list.completedReviews'),
						'value' => 10,
						'min' => 0,
						'max' => 20,
						// The slider component expects variables in the format {var}
						'formatter' => str_replace('$', '', __('common.moreThan')),
					],
					[
						'param' => 'daysSinceLastAssignment',
						'title' => __('reviewer.list.daysSinceLastAssignmentDescription'),
						'value' => [0, 365],
						'min' => 0,
						'max' => 365,
					],
					[
						'param' => 'reviewsActive',
						'title' => __('reviewer.list.activeReviewsDescription'),
						'value' => [0, 20],
						'min' => 0,
						'max' => 20,
					],
					[
						'param' => 'averageCompletion',
						'title' => __('reviewer.list.averageCompletion'),
						'value' => 75,
						'min' => 0,
						'max' => 75,
					],
				],
			],
		];
		$config['i18n'] = array_merge($config['i18n'], array(
			'search' => __('common.search'),
			'clearSearch' => __('common.clearSearch'),
			'empty' => __('reviewer.list.empty'),
			'itemsOfTotal' => __('reviewer.list.itemsOfTotal'),
			'itemCount' => __('reviewer.list.count'),
			'loadMore' => __('grid.action.moreItems'),
			'loading' => __('common.loading'),
			'filter' => __('common.filter'),
			'filterAdd' => __('common.filterAdd'),
			'filterRemove' => __('common.filterRemove'),
			'filterRating' => __('reviewer.list.filterRating'),
			'activeReviews' => __('reviewer.list.activeReviews'),
			'activeReviewsDescription' => __('reviewer.list.activeReviewsDescription'),
			'completedReviews' => __('reviewer.list.completedReviews'),
			'declinedReviews' => __('reviewer.list.declinedReviews'),
			'reviewerRating' => __('reviewer.list.reviewerRating'),
			'daySinceLastAssignment' => __('reviewer.list.daySinceLastAssignment'),
			'daysSinceLastAssignment' => __('reviewer.list.daysSinceLastAssignment'),
			'daysSinceLastAssignmentDescription' => __('reviewer.list.daysSinceLastAssignmentDescription'),
			'averageCompletion' => __('reviewer.list.averageCompletion'),
			'neverAssigned' => __('reviewer.list.neverAssigned'),
			'currentlyAssigned' => __('reviewer.list.currentlyAssigned'),
			'warnOnAssign' => __('reviewer.list.warnOnAssign'),
			'warnOnAssignUnlock' => __('reviewer.list.warnOnAssignUnlock'),
			'reviewInterests' => __('reviewer.list.reviewInterests'),
			'gossip' => __('user.gossip'),
			'biography' => __('reviewer.list.biography'),
			'listSeparator' => __('common.commaListSeparator'),
			'viewMore' => __('list.viewMore'),
			'viewLess' => __('list.viewLess'),
			'paginationLabel' => __('common.pagination.label'),
			'goToLabel' => __('common.pagination.goToPage'),
			'pageLabel' => __('common.pageNumber'),
			'nextPageLabel' => __('common.pagination.next'),
			'previousPageLabel' => __('common.pagination.previous'),
		));

		return $config;
	}

	/**
	 * Helper method to get the items property according to the self::$getParams
	 *
	 * @param Request $request
	 * @return array
	 */
	public function getItems($request) {
		$userService = \Services::get('user');
		$reviewers = $userService->getReviewers($this->_getItemsParams());
		$items = [];
		if (!empty($reviewers)) {
			foreach ($reviewers as $reviewer) {
				$items[] = $userService->getReviewerSummaryProperties($reviewer, ['request' => $request]);
			}
		}

		return $items;
	}

	/**
	 * Helper method to get the itemsMax property according to self::$getParams
	 *
	 * @return int
	 */
	public function getItemsMax() {
		return \Services::get('user')->getReviewersMax($this->_getItemsParams());
	}

	/**
	 * Helper method to compile initial params to get items
	 *
	 * @return array
	 */
	protected function _getItemsParams() {
		return array_merge(
			[
				'count' => $this->count,
				'offset' => 0,
			],
			$this->getParams
		);
	}
}
