<?php
/**
 * @file components/listPanels/users/SelectReviewerListPanel.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectReviewerListPanel
 * @ingroup classes_controllers_list
 *
 * @brief A class for loading a panel to select a reviewer.
 */
import('lib.pkp.classes.components.listPanels.SelectListPanel');
import('classes.core.Services');

class SelectReviewerListPanel extends SelectListPanel {

	/** @var int Count of items to retrieve in initial page/request */
	public $_count = 15;

	/** @var array Query parameters to pass with every GET request */
	public $_getParams = array(
		'roleIds' => array(ROLE_ID_REVIEWER),
	);

	/** @var string Used to generate URLs to API endpoints for this component. */
	public $_apiUrl = 'users/reviewers';

	/** @var array List of user IDs already assigned as a reviewer to this submission */
	public $_currentlyAssigned = array();

	/** @var array List of user IDs which may not be suitable for blind review because of existing access to author details */
	public $_warnOnAssignment = array();

	/**
	 * @copydoc ListPanel::init()
	 */
	public function init($args = array()) {
		parent::init($args);
		$this->_count = isset($args['count']) ? (int) $args['count'] : $this->_count;
		$this->_currentlyAssigned = !empty($args['currentlyAssigned']) ? $args['currentlyAssigned'] : $this->_currentlyAssigned;
		$this->_warnOnAssignment = !empty($args['warnOnAssignment']) ? $args['warnOnAssignment'] : $this->_warnOnAssignment;
	}

	/**
	 * @copydoc SelectListPanel::getConfig()
	 */
	public function getConfig() {

		$config = parent::getConfig();

		$config['apiUrl'] = $this->_apiUrl;
		$config['itemsMax'] = $this->getItemsMax();
		$config['count'] = $this->_count;
		$config['currentlyAssigned'] = $this->_currentlyAssigned;
		$config['warnOnAssignment'] = $this->_warnOnAssignment;

		$config['i18n'] = array_merge($config['i18n'], array(
			'search' => __('common.search'),
			'clearSearch' => __('common.clearSearch'),
			'itemsOfTotal' => __('reviewer.list.itemsOfTotal'),
			'itemCount' => __('reviewer.list.count'),
			'loadMore' => __('grid.action.moreItems'),
			'loading' => __('common.loading'),
			'filter' => __('common.filter'),
			'filterAdd' => __('common.filterAdd'),
			'filterRemove' => __('common.filterRemove'),
			'filterRating' => __('reviewer.list.filterRating'),
			'lessThan' => __('common.lessThan'),
			'moreThan' => __('common.moreThan'),
			'activeReviews' => __('reviewer.list.activeReviews'),
			'activeReviewsDescription' => __('reviewer.list.activeReviewsDescription'),
			'completedReviews' => __('reviewer.list.completedReviews'),
			'declinedReviews' => __('reviewer.list.declinedReviews'),
			'reviewerRating' => __('reviewer.list.reviewerRating'),
			'daysSinceLastAssignment' => __('reviewer.list.daysSinceLastAssignment'),
			'daySinceLastAssignment' => __('reviewer.list.daySinceLastAssignment'),
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
		));

		if ($this->_notice) {
			$config['i18n']['notice'] = __($this->_notice);
		}

		return $config;
	}

	/**
	 * @copydoc SelectListPanel::getItems()
	 */
	public function getItems() {
		$request = Application::get()->getRequest();

		$userService = Services::get('user');
		$reviewers = $userService->getReviewers($this->_getItemsParams());
		$items = array();
		if (!empty($reviewers)) {
			$propertyArgs = array(
				'request' => $request,
			);
			foreach ($reviewers as $reviewer) {
				$items[] = $userService->getReviewerSummaryProperties($reviewer, $propertyArgs);
			}
		}

		return $items;
	}

	/**
	 * @copydoc SelectListPanel::getItemsMax()
	 */
	public function getItemsMax() {
		return Services::get('user')->getReviewersMax($this->_getItemsParams());
	}

	/**
	 * @copydoc SelectListPanel::_getItemsParams()
	 */
	protected function _getItemsParams() {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

		return array_merge(
			array(
				'contextId' => $contextId,
				'count' => $this->_count,
				'offset' => 0,
			),
			$this->_getParams
		);
	}
}
