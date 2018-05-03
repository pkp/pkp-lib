<?php
/**
 * @file controllers/list/users/SelectReviewerListHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectReviewerListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A class for loading a panel to select a reviewer.
 */
import('lib.pkp.controllers.list.SelectListHandler');
import('classes.core.ServicesContainer');

class SelectReviewerListHandler extends SelectListHandler {

	/** @var int Count of items to retrieve in initial page/request */
	public $_count = 15;

	/** @var array Query parameters to pass with every GET request */
	public $_getParams = array(
		'roleIds' => array(ROLE_ID_REVIEWER),
	);

	/** @var string Used to generate URLs to API endpoints for this component. */
	public $_apiPath = 'users/reviewers';

	/** @var array List of user IDs already assigned as a reviewer to this submission */
	public $_currentlyAssigned = array();

	/** @var array List of user IDs which may not be suitable for blind review because of existing access to author details */
	public $_warnOnAssignment = array();

	/**
	 * @copydoc ListHandler::init()
	 */
	public function init($args = array()) {
		parent::init($args);
		$this->_count = isset($args['count']) ? (int) $args['count'] : $this->_count;
		$this->_currentlyAssigned = !empty($args['currentlyAssigned']) ? $args['currentlyAssigned'] : $this->_currentlyAssigned;
		$this->_warnOnAssignment = !empty($args['warnOnAssignment']) ? $args['warnOnAssignment'] : $this->_warnOnAssignment;
	}

	/**
	 * @copydoc SelectListHandler::getConfig()
	 */
	public function getConfig() {

		$config = parent::getConfig();

		$config['apiPath'] = $this->_apiPath;
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
			'listSeparator' => __('common.listSeparator'),
			'viewMore' => __('list.viewMore'),
			'viewLess' => __('list.viewLess'),
		));

		if ($this->_notice) {
			$config['i18n']['notice'] = __($this->_notice);
		}

		return $config;
	}

	/**
	 * @copydoc SelectListHandler::getItems()
	 */
	public function getItems() {
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$userService = ServicesContainer::instance()->get('user');
		$reviewers = $userService->getReviewers($context->getId(), $this->_getItemsParams());
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
	 * @copydoc SelectListHandler::getItemsMax()
	 */
	public function getItemsMax() {
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		return ServicesContainer::instance()
			->get('user')
			->getReviewersMaxCount($context->getId(), $this->_getItemsParams());
	}

	/**
	 * @copydoc SelectListHandler::_getItemsParams()
	 */
	protected function _getItemsParams() {
		return array_merge(
			array(
				'count' => $this->_count,
				'offset' => 0,
			),
			$this->_getParams
		);
	}
}
