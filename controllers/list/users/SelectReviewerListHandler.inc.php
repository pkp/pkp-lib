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

	/** @var array Query parameters to pass with every GET request */
	public $_getParams = array(
		'roleIds' => array(ROLE_ID_REVIEWER),
	);

	/**
	 * API endpoint path
	 *
	 * Used to generate URLs to API endpoints for this component.
	 *
	 * @param string
	 */
	public $_apiPath = 'users/reviewers';

	/**
	 * Retrieve the configuration data to be used when initializing this
	 * handler on the frontend
	 *
	 * @return array Configuration data
	 */
	public function getConfig() {

		$data = parent::getConfig();

		$data['apiPath'] = $this->_apiPath;
		$data['collection'] = $this->_items;

		$data['i18n'] = array_merge($data['i18n'], array(
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
			'activeReviews' => __('reviewer.list.activeReviews'),
			'activeReviewsDescription' => __('reviewer.list.activeReviewsDescription'),
			'completedReviews' => __('reviewer.list.completedReviews'),
			'reviewerRating' => __('reviewer.list.reviewerRating'),
			'daysSinceLastAssignment' => __('reviewer.list.daysSinceLastAssignment'),
			'daySinceLastAssignment' => __('reviewer.list.daySinceLastAssignment'),
			'daysSinceLastAssignmentDescription' => __('reviewer.list.daysSinceLastAssignmentDescription'),
			'averageCompletion' => __('reviewer.list.averageCompletion'),
			'neverAssigned' => __('reviewer.list.neverAssigned'),
			'reviewInterests' => __('reviewer.list.reviewInterests'),
			'listSeparator' => __('common.listSeparator'),
			'viewMore' => __('review.list.viewMore'),
			'viewLess' => __('review.list.viewLess'),
		));

		if ($this->_notice) {
			$data['i18n']['notice'] = __($this->_notice);
		}

		return $data;
	}

	/**
	 * @copydoc ListPanel::getItems()
	 */
	public function getItems() {

		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$params = array_merge(
			array(
				'count' => $this->_count,
				'offset' => 0,
			),
			$this->_getParams
		);

		$userService = ServicesContainer::instance()->get('user');
		$reviewers = $userService->getReviewers($context->getId(), $params);
		$items = array();
		if (!empty($reviewers)) {
			$propertyArgs = array(
				'request' => $request,
			);
			foreach ($reviewers as $reviewer) {
				$items[] = $userService->getReviewerSummaryProperties($reviewer, $propertyArgs);
			}
		}

		return array(
			'items' => $items,
			'maxItems' => $userService->getReviewersMaxCount($context->getId(), $params),
		);
	}
}
