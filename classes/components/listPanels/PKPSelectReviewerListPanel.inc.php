<?php
/**
 * @file components/listPanels/PKPSelectReviewerListPanel.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSelectReviewerListPanel
 * @ingroup classes_controllers_list
 *
 * @brief A class for loading a panel to select a reviewer.
 */

namespace PKP\components\listPanels;

class PKPSelectReviewerListPanel extends ListPanel {

	/** @var string URL to the API endpoint where items can be retrieved */
	public $apiUrl = '';

	/** @var integer Number of items to show at one time */
	public $count = 30;

	/** @var array List of user IDs already assigned as a reviewer to this submission */
	public $currentlyAssigned = [];

	/** @var array Query parameters to pass if this list executes GET requests  */
	public $getParams = [];

	/** @var integer Count of total items available for list */
	public $itemsMax = 0;

	/** @var string Name of the input field*/
	public $selectorName = '';

	/** @var array List of user IDs which may not be suitable for anonymous review because of existing access to author details */
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
		$config['apiUrl'] = $this->apiUrl;
		$config['count'] = $this->count;
		$config['currentlyAssigned'] = $this->currentlyAssigned;
		$config['selectorName'] = $this->selectorName;
		$config['warnOnAssignment'] = $this->warnOnAssignment;
		$config['filters'] = [
			[
				'param' => 'reviewerRating',
				'title' => __('reviewer.list.filterRating'),
				'value' => 3,
				'min' => 1,
				'max' => 5,
				'useStars' => true,
				'valueLabel' => '{$value/5}',
			],
			[
				'param' => 'reviewsCompleted',
				'title' => __('reviewer.list.completedReviews'),
				'value' => 10,
				'min' => 0,
				'max' => 20,
				'valueLabel' => __('common.moreThan'),
			],
			[
				'param' => 'daysSinceLastAssignment',
				'title' => __('reviewer.list.daysSinceLastAssignmentDescription'),
				'value' => [0, 365],
				'min' => 0,
				'max' => 365,
				'filterType' => 'filter-slider-multirange',
				'valueLabel' => __('common.range'),
				'moreThanLabel' => __('common.moreThanOnly'),
				'lessThanLabel' => __('common.lessThanOnly'),
			],
			[
				'param' => 'reviewsActive',
				'title' => __('reviewer.list.activeReviewsDescription'),
				'value' => [0, 20],
				'min' => 0,
				'max' => 20,
				'filterType' => 'filter-slider-multirange',
				'valueLabel' => __('common.range'),
				'moreThanLabel' => __('common.moreThanOnly'),
				'lessThanLabel' => __('common.lessThanOnly'),
			],
			[
				'param' => 'averageCompletion',
				'title' => __('reviewer.list.averageCompletion'),
				'value' => 75,
				'min' => 0,
				'max' => 75,
				'valueLabel' => __('common.lessThan'),
			],
		];

		if (!empty($this->getParams)) {
			$config['getParams'] = $this->getParams;
		}

		$config['itemsMax'] = $this->itemsMax;

		$config['activeReviewsCountLabel'] = __('reviewer.list.activeReviews');
		$config['activeReviewsLabel'] = __('reviewer.list.activeReviewsDescription');
		$config['averageCompletionLabel'] = __('reviewer.list.averageCompletion');
		$config['biographyLabel'] = __('reviewer.list.biography');
		$config['cancelledReviewsLabel'] = __('reviewer.list.cancelledReviews');
		$config['completedReviewsLabel'] = __('reviewer.list.completedReviews');
		$config['currentlyAssignedLabel'] = __('reviewer.list.currentlyAssigned');
		$config['daySinceLastAssignmentLabel'] = __('reviewer.list.daySinceLastAssignment');
		$config['daysSinceLastAssignmentLabel'] = __('reviewer.list.daysSinceLastAssignment');
		$config['daysSinceLastAssignmentDescriptionLabel'] = __('reviewer.list.daysSinceLastAssignmentDescription');
		$config['declinedReviewsLabel'] = __('reviewer.list.declinedReviews');
		$config['emptyLabel'] = __('reviewer.list.empty');
		$config['gossipLabel'] = __('user.gossip');
		$config['neverAssignedLabel'] = __('reviewer.list.neverAssigned');
		$config['reviewerRatingLabel'] = __('reviewer.list.reviewerRating');
		$config['reviewInterestsLabel'] = __('reviewer.list.reviewInterests');
		$config['selectReviewerLabel'] = __('editor.submission.selectReviewer');
		$config['warnOnAssignmentLabel'] = __('reviewer.list.warnOnAssign');
		$config['warnOnAssignmentUnlockLabel'] = __('reviewer.list.warnOnAssignUnlock');

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
				'offset' => 0,
				'count' => $this->count,
			],
			$this->getParams
		);
	}
}
