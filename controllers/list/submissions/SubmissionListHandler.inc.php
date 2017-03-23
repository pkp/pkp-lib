<?php
/**
 * @file classes/controllers/list/submissions/SubmissionListHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A base class for submission list handlers. Sub-classes should extend
 *  this class by defining a `getItems` method to fetch a particular set of
 *  submissions (eg - active, archived, assigned to me).
 */
import('lib.pkp.controllers.list.ListHandler');
import('lib.pkp.classes.db.DBResultRange');

class SubmissionListHandler extends ListHandler {

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
	public $_apiPath = 'submissions';

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

		$config['i18n']['title'] = __($this->_title);
		$config['i18n']['add'] = __('submission.submit.newSubmissionSingle');
		$config['i18n']['search'] = __('common.search');
		$config['i18n']['itemCount'] = __('submission.list.count');
		$config['i18n']['itemsOfTotal'] = __('submission.list.itemsOfTotal');
		$config['i18n']['loadMore'] = __('grid.action.moreItems');
		$config['i18n']['loading'] = __('common.loading');
		$config['i18n']['delete'] = __('common.delete');
		$config['i18n']['infoCenter'] = __('submission.list.infoCenter');
		$config['i18n']['ok'] = __('common.ok');
		$config['i18n']['cancel'] = __('common.cancel');
		$config['i18n']['confirmDelete'] = __('common.confirmDelete');
		$config['i18n']['responseDue'] = __('submission.list.responseDue');
		$config['i18n']['reviewDue'] = __('submission.list.reviewDue');

		// Attach a CSRF token for post requests
		$config['csrfToken'] = $request->getSession()->getCSRFToken();

		return $config;
	}

	/**
	 * API Route: delete a submission
	 *
	 * @param $args int ID of the submission to delete
	 * @param $request PKPRequest
	 * return JSONMessage
	 */
	public function delete($args, $request) {

		if (!$request->checkCSRF()) {
			return new JSONMessage(false);
		}

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById(
			(int) $request->getUserVar('id')
		);

		if (!$submission) {
			return new JSONMessage(false);
		}

		$submissionDao->deleteById($submission->getId());

		$json = DAO::getDataChangedEvent($submission->getId());
		$json->setGlobalEvent('submissionDeleted', array('id' => $submission->getId()));
		return $json;
	}

	/**
	 * Helper function to retrieve items
	 *
	 * @return array Items requested
	 */
	public function getItems() {

		$params = array_merge(
			array(
				'count' => $this->_count,
				'page' => $this->_page,
			),
			$this->_getParams
		);

		$submissionDao = Application::getSubmissionDAO();

		return $submissionDao->get(
			$this,
			$params,
			Application::getRequest()->getContext()->getId()
		);
	}
}
