<?php
/**
 * @file classes/controllers/list/submissions/SubmissionsListHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A base class for submission list handlers. Sub-classes should extend
 *  this class by defining a `getItems` method to fetch a particular set of
 *  submissions (eg - active, archived, assigned to me).
 */
import('lib.pkp.controllers.list.ListHandler');
import('lib.pkp.classes.db.DBResultRange');

class SubmissionsListHandler extends ListHandler {

	/**
	 * Count of items to retrieve in initial page/request
	 *
	 * @param int
	 */
	public $_count = 20;

	/**
	 * Query parameters to pass with every GET request
	 *
	 * @param array
	 */
	public $_getParams = array();

	/**
	 * API endpoint path
	 *
	 * Used to generate URLs to API endpoints for this component.
	 *
	 * @param string
	 */
	public $_apiPath = 'backend/submissions';

	/**
	 * Initialize the handler with config parameters
	 *
	 * @param array $args Configuration params
	 */
	public function init( $args = array() ) {
		parent::init($args);

		$this->_count = isset($args['count']) ? (int) $args['count'] : $this->_count;
		$this->_getParams = isset($args['getParams']) ? $args['getParams'] : $this->_getParams;
	}

	/**
	 * Retrieve the configuration data to be used when initializing this
	 * handler on the frontend
	 *
	 * @return array Configuration data
	 */
	public function getConfig() {

		$request = Application::getRequest();

		$config = array();

		if ($this->_lazyLoad) {
			$config['lazyLoad'] = true;
		} else {
			$config['collection'] = $this->getItems();
		}

		// URL to add a new submission
		$config['addUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_PAGE,
			null,
			'submission',
			'wizard'
		);

		// URL to view info center for a submission
		$config['infoUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'informationCenter.SubmissionInformationCenterHandler',
			'viewInformationCenter',
			null,
			array('submissionId' => '__id__')
		);

		$config['apiPath'] = $this->_apiPath;

		$config['count'] = $this->_count;
		$config['page'] = 1;

		$config['getParams'] = $this->_getParams;

		// Load grid localisation files
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_GRID);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);

		$config['i18n']['title'] = __($this->_title);
		$config['i18n']['add'] = __('submission.submit.newSubmissionSingle');
		$config['i18n']['search'] = __('common.search');
		$config['i18n']['itemCount'] = __('submission.list.count');
		$config['i18n']['itemsOfTotal'] = __('submission.list.itemsOfTotal');
		$config['i18n']['loadMore'] = __('grid.action.moreItems');
		$config['i18n']['loading'] = __('common.loading');
		$config['i18n']['incomplete'] = __('submissions.incomplete');
		$config['i18n']['delete'] = __('common.delete');
		$config['i18n']['infoCenter'] = __('submission.list.infoCenter');
		$config['i18n']['yes'] = __('common.yes');
		$config['i18n']['no'] = __('common.no');
		$config['i18n']['deleting'] = __('common.deleting');
		$config['i18n']['confirmDelete'] = __('submission.list.confirmDelete');
		$config['i18n']['responseDue'] = __('submission.list.responseDue');
		$config['i18n']['reviewDue'] = __('submission.list.reviewDue');

		// Attach a CSRF token for post requests
		$config['csrfToken'] = $request->getSession()->getCSRFToken();

		return $config;
	}

	/**
	 * Helper function to retrieve items
	 *
	 * @return array Items requested
	 */
	public function getItems() {

		$context = Application::getRequest()->getContext();
		$contextId = $context ? $context->getId() : 0;

		$params = array_merge(
			array(
				'count' => $this->_count,
				'offset' => 0,
			),
			$this->_getParams
		);

		import('lib.pkp.classes.core.ServicesContainer');
		return ServicesContainer::instance()
				->get('submission')
				->getSubmissionList($contextId, $params);
	}
}
