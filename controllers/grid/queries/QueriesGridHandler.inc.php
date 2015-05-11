<?php

/**
 * @file controllers/grid/queries/QueriesGridHandler.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueriesGridHandler
 * @ingroup controllers_grid_query
 *
 * @brief base PKP class to handle query grid requests.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.controllers.grid.queries.QueriesGridCellProvider');


// Link action & modal classes
import('lib.pkp.classes.linkAction.request.AjaxModal');

class QueriesGridHandler extends GridHandler {

	/** @var integer */
	var $_stageId;

	/**
	 * Constructor
	 */
	function QueriesGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_AUTHOR, ROLE_ID_SECTION_EDITOR),
			array('fetchGrid', 'fetchRow', 'addQuery', 'editQuery', 'updateQuery', 'readQuery', 'cancelQuery'));
	}


	//
	// Getters/Setters
	//
	/**
	 * Get the authorized submission.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}

	/**
	 * Get the review stage id.
	 * @return integer
	 */
	function getStageId() {
		return $this->_stageId;
	}

	//
	// Overridden methods from PKPHandler.
	// Note: this is subclassed in application-specific grids.
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$stageId = $request->getUserVar('stageId'); // This is being validated in WorkflowStageAccessPolicy
		$this->_stageId = (int)$stageId;

		// Get the stage access policy
		import('classes.security.authorization.WorkflowStageAccessPolicy');
		$workflowStageAccessPolicy = new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId);
		$this->addPolicy($workflowStageAccessPolicy);
		return parent::authorize($request, $args, $roleAssignments);
	}

	/*
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);
		import('lib.pkp.controllers.grid.queries.QueriesGridCellProvider');

		$this->setTitle('submission.queries');
		$this->setInstructions('submission.queriesDescription');

		// Load pkp-lib translations
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_DEFAULT
		);

		// Columns
		import('lib.pkp.controllers.grid.queries.QueryTitleGridColumn');
		$cellProvider = new QueriesGridCellProvider();
		$this->addColumn(new QueryTitleGridColumn($this->getSubmission(), $this->getStageId()));

		$this->addColumn(
			new GridColumn(
				'replies',
				'submission.query.replies',
				null,
				null,
				$cellProvider,
				array('width' => 10, 'alignment' => COLUMN_ALIGNMENT_CENTER)
			)
		);
		$this->addColumn(
			new GridColumn(
				'from',
				'submission.query.from',
				null,
				null,
				$cellProvider,
				array('html' => TRUE)
			)
		);
		$this->addColumn(
			new GridColumn(
				'lastReply',
				'submission.query.lastReply',
				null,
				null,
				$cellProvider,
				array('html' => TRUE)
			)
		);

		$this->addColumn(
			new GridColumn(
				'closed',
				'submission.query.closed',
				null,
				'controllers/grid/queries/threadClosed.tpl',
				$cellProvider,
				array('width' => 40, 'alignment' => COLUMN_ALIGNMENT_CENTER)
			)
		);

		$router = $request->getRouter();
		$actionArgs = $this->getRequestArgs();
		$this->addAction(
				new LinkAction(
					'addQuery',
					new AjaxModal(
						$router->url($request, null, null, 'addQuery', null, $actionArgs),
						__('grid.action.addQuery'),
						'modal_add_query'
					),
				__('grid.action.addQuery'),
				'add_user'
			)
		);
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @see GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		$features = parent::initFeatures($request, $args);
		import('lib.pkp.classes.controllers.grid.feature.OrderGridItemsFeature');
		$features[] = new OrderGridItemsFeature();

		return $features;
	}

	/**
	 * @copydoc GridHandler::getDataElementSequence()
	 */
	function getDataElementSequence($row) {
		return $row->getSequence();
	}

	/**
	 * @copydoc GridHandler::getRowInstance()
	 * @return QueriesGridRow
	 */
	function getRowInstance() {
		import('lib.pkp.controllers.grid.queries.QueriesGridRow');
		return new QueriesGridRow($this->getSubmission(), $this->getStageId());
	}

	/**
	 * Get the arguments that will identify the data in the grid.
	 * Overridden by child grids.
	 * @return array
	 */
	function getRequestArgs() {
		$submission = $this->getSubmission();
		return array(
			'submissionId' => $submission->getId(),
			'stageId' => $this->getStageId(),
		);
	}

	/**
	 * Fetches the application-specific submission id from the request object.
	 * Should be overridden by subclasses.
	 * @param PKPRequest $request
	 * @return int
	 */
	function getRequestedSubmissionId($request) {
		return $request->getUserVar('submissionId');
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	function loadData($request, $filter = null) {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stage = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		$queryDao = DAORegistry::getDAO('SubmissionFileQueryDAO');
		return $queryDao->getBySubmissionId($submission->getId(), $stage, true);
	}

	//
	// Public Query Grid Actions
	//
	/**
	 * Add a query
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function addQuery($args, $request) {
		// Identify the query to be updated
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

		// addQuery called, so initialize empty query and pass to form.
		$submissionFileQueryDao = DAORegistry::getDAO('SubmissionFileQueryDAO');
		$query = $submissionFileQueryDao->newDataObject();
		$query->setSubmissionId($submission->getId());
		$query->setStageId($stageId);
		$user = $request->getUser();

		$query->setUserId($user->getId());
		$query->setDatePosted(Core::getCurrentDate());
		$query->setParentQueryId(0);
		$queryId = $submissionFileQueryDao->insertObject($query);
		$query->setId($queryId);

		// Form handling
		import('lib.pkp.controllers.grid.queries.form.QueryForm');
		$queryForm = new QueryForm($submission, $stageId, $query);
		$queryForm->initData();

		return new JSONMessage(true, $queryForm->fetch($request));
	}

	/**
	 * Cancels a query, called via JS Handler callback.  Deletes the query.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function cancelQuery($args, $request) {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		// Identify the query to be read
		$queryId = $request->getUserVar('queryId');
		if ($queryId) {
			$queryDao = DAORegistry::getDAO('SubmissionFileQueryDAO');
			$query = $queryDao->getById($queryId, $submission->getId());
			if ($query) {
				$queryDao->deleteObject($query);
				return new JSONMessage(true);
			}
		}
	}

	/**
	 * Read a query
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function readQuery($args, $request) {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		// Identify the query to be read
		$queryId = $request->getUserVar('queryId');
		if ($queryId) {
			$queryDao = DAORegistry::getDAO('SubmissionFileQueryDAO');
			$query = $queryDao->getById($queryId, $submission->getId());
		}

		if (!$query) {
			fatalError('Invalid Query id.');
		}

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('query', $query);

		return new JSONMessage(true, $templateMgr->fetch('controllers/grid/queries/readQuery.tpl'));
	}

	/**
	 * Edit a query
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateQuery($args, $request) {
		// Identify the query to be updated
		$queryId = $request->getUserVar('queryId');
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);


		$queryDao = DAORegistry::getDAO('SubmissionFileQueryDAO');
		$query = $queryDao->getById($queryId, $submission->getId());

		// Form handling
		import('lib.pkp.controllers.grid.queries.form.QueryForm');
		$queryForm = new QueryForm($submission, $stageId, $query);
		$queryForm->readInputData($request);
		if ($queryForm->validate()) {
			$queryId = $queryForm->execute();

			if(!isset($query)) {
				// This is a new query
				$query = $queryDao->getById($queryId, $submission->getId());
				// New added query action notification content.
				$notificationContent = __('notification.addedQuery');
			} else {
				// Query edit action notification content.
				$notificationContent = __('notification.editedQuery');
			}

			// Create trivial notification.
			$currentUser = $request->getUser();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => $notificationContent));

			// Prepare the grid row data
			$row = $this->getRowInstance();
			$row->setGridId($this->getId());
			$row->setId($queryId);
			$row->setData($query);
			$row->initialize($request);

			// Render the row into a JSON response
			return DAO::getDataChangedEvent($queryId);
		} else {
			return new JSONMessage(true, $queryForm->fetch($request));
		}
	}
}

?>
