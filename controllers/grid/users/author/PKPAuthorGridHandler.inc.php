<?php

/**
 * @file controllers/grid/users/author/PKPAuthorGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorGridHandler
 * @ingroup controllers_grid_users_author
 *
 * @brief base PKP class to handle author grid requests.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.controllers.grid.users.author.PKPAuthorGridCellProvider');


// Link action & modal classes
import('lib.pkp.classes.linkAction.request.AjaxModal');

class PKPAuthorGridHandler extends GridHandler {
	/** @var Submission */
	var $_submission;

	/** @var boolean */
	var $_readOnly;

	/**
	 * Constructor
	 */
	function PKPAuthorGridHandler() {
		parent::GridHandler();
	}


	//
	// Getters/Setters
	//
	/**
	 * Get the submission associated with this author grid.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Set the Submission
	 * @param Submission
	 */
	function setSubmission($submission) {
		$this->_submission = $submission;
	}

	/**
	 * Get whether or not this grid should be 'read only'
	 * @return boolean
	 */
	function getReadOnly() {
		return $this->_readOnly;
	}

	/**
	 * Set the boolean for 'read only' status
	 * @param boolean
	 */
	function setReadOnly($readOnly) {
		$this->_readOnly = $readOnly;
	}


	//
	// Overridden methods from PKPHandler.
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		return parent::authorize($request, $args, $roleAssignments);
	}

	/*
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		// Retrieve the authorized submission.
		$this->setSubmission($this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION));

		$this->setTitle('submission.contributors');
		$this->setInstructions('submission.contributorsDescription');

		// Load pkp-lib translations
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_DEFAULT
		);

		if ($this->canAdminister()) {
			$this->setReadOnly(false);
			// Grid actions
			$router = $request->getRouter();
			$actionArgs = $this->getRequestArgs();
			$this->addAction(
				new LinkAction(
					'addAuthor',
					new AjaxModal(
						$router->url($request, null, null, 'addAuthor', null, $actionArgs),
						__('grid.action.addContributor'),
						'modal_add_user'
					),
					__('grid.action.addContributor'),
					'add_user'
				)
			);
		} else {
			$this->setReadOnly(true);
		}

		// Columns
		$cellProvider = new PKPAuthorGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'name',
				'author.users.contributor.name',
				null,
				null,
				$cellProvider,
				array('width' => 40, 'alignment' => COLUMN_ALIGNMENT_LEFT)
			)
		);
		$this->addColumn(
			new GridColumn(
				'email',
				'author.users.contributor.email',
				null,
				null,
				$cellProvider
			)
		);
		$this->addColumn(
			new GridColumn(
				'role',
				'author.users.contributor.role',
				null,
				null,
				$cellProvider
			)
		);
		$this->addColumn(
			new GridColumn(
				'principalContact',
				'author.users.contributor.principalContact',
				null,
				'controllers/grid/users/author/primaryContact.tpl',
				$cellProvider
			)
		);
		$this->addColumn(
			new GridColumn(
				'includeInBrowse',
				'author.users.contributor.includeInBrowse',
				null,
				'controllers/grid/users/author/includeInBrowse.tpl',
				$cellProvider
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
		if ($this->canAdminister()) {
			import('lib.pkp.classes.controllers.grid.feature.OrderGridItemsFeature');
			$features[] = new OrderGridItemsFeature();
		}
		return $features;
	}

	/**
	 * @copydoc GridHandler::getDataElementSequence()
	 */
	function getDataElementSequence($row) {
		return $row->getSequence();
	}

	/**
	 * @copydoc GridHandler::setDataElementSequence()
	 */
	function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence) {
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$submission = $this->getSubmission();
		$author = $authorDao->getById($rowId, $submission->getId());
		$author->setSequence($newSequence);
		$authorDao->updateObject($author);
	}

	/**
	 * @copydoc GridHandler::getRowInstance()
	 * @return AuthorGridRow
	 */
	function getRowInstance() {
		return new AuthorGridRow($this->getSubmission(), $this->getReadOnly());
	}

	/**
	 * Get the arguments that will identify the data in the grid.
	 * Overridden by child grids.
	 * @return array
	 */
	function getRequestArgs() {
		$submission = $this->getSubmission();
		return array(
			'submissionId' => $submission->getId()
		);
	}

	/**
	 * Determines if there should be an 'add user' action on this grid.
	 * Overridden by child grids.
	 * @return boolean
	 */
	function canAdminister() {
		return false;
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
		$submission = $this->getSubmission();
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		return $authorDao->getBySubmissionId($submission->getId(), true);
	}

	//
	// Public Author Grid Actions
	//
	/**
	 * An action to manually add a new author
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function addAuthor($args, $request) {
		// Calling editAuthor() with an empty row id will add
		// a new author.
		return $this->editAuthor($args, $request);
	}

	/**
	 * Edit a author
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editAuthor($args, $request) {
		// Identify the author to be updated
		$authorId = $request->getUserVar('authorId');
		$submission = $this->getSubmission();

		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$author = $authorDao->getById($authorId, $submission->getId());

		// Form handling
		import('lib.pkp.controllers.grid.users.author.form.AuthorForm');
		$authorForm = new AuthorForm($submission, $author, 'submissionId');
		$authorForm->initData();

		return new JSONMessage(true, $authorForm->fetch($request));
	}

	/**
	 * Edit a author
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateAuthor($args, $request) {
		// Identify the author to be updated
		$authorId = $request->getUserVar('authorId');
		$submission = $this->getSubmission();

		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$author = $authorDao->getById($authorId, $submission->getId());

		// Form handling
		import('lib.pkp.controllers.grid.users.author.form.AuthorForm');
		$authorForm = new AuthorForm($submission, $author, 'submissionId');
		$authorForm->readInputData();
		if ($authorForm->validate()) {
			$authorId = $authorForm->execute();

			if(!isset($author)) {
				// This is a new contributor
				$author = $authorDao->getById($authorId, $submission->getId());
				// New added author action notification content.
				$notificationContent = __('notification.addedAuthor');
			} else {
				// Author edition action notification content.
				$notificationContent = __('notification.editedAuthor');
			}

			// Create trivial notification.
			$currentUser = $request->getUser();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => $notificationContent));

			// Prepare the grid row data
			$row = $this->getRowInstance();
			$row->setGridId($this->getId());
			$row->setId($authorId);
			$row->setData($author);
			$row->initialize($request);

			// Render the row into a JSON response
			if($author->getPrimaryContact()) {
				// If this is the primary contact, redraw the whole grid
				// so that it takes the checkbox off other rows.
				return DAO::getDataChangedEvent();
			} else {
				return DAO::getDataChangedEvent($authorId);
			}
		} else {
			return new JSONMessage(true, $authorForm->fetch($request));
		}
	}

	/**
	 * Delete a author
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteAuthor($args, $request) {
		// Identify the submission Id
		$submissionId = $this->getRequestedSubmissionId($request);
		// Identify the author to be deleted
		$authorId = $request->getUserVar('authorId');

		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$result = $authorDao->deleteById($authorId, $submissionId);

		if ($result) {
			return DAO::getDataChangedEvent($authorId);
		} else {
			return new JSONMessage(false, __('submission.submit.errorDeletingAuthor'));
		}
	}

	/**
	 * Add a user with data initialized from an existing author.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function addUser($args, $request) {
		// Identify the author Id.
		$authorId = $request->getUserVar('authorId');

		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$userDao = DAORegistry::getDAO('UserDAO');
		$author = $authorDao->getById($authorId);

		if ($author !== null && $userDao->userExistsByEmail($author->getEmail())) {
			// We don't have administrative rights over this user.
			return new JSONMessage(false, __('grid.user.cannotAdminister'));
		} else {
			// Form handling.
			import('lib.pkp.controllers.grid.settings.user.form.UserDetailsForm');
			$userForm = new UserDetailsForm($request, null, $author);
			$userForm->initData($args, $request);

			return new JSONMessage(true, $userForm->display($args, $request));
		}
	}
}

?>
