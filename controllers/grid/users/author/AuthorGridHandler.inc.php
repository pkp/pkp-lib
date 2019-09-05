<?php

/**
 * @file controllers/grid/users/author/AuthorGridHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorGridHandler
 * @ingroup controllers_grid_users_author
 *
 * @brief base PKP class to handle author grid requests.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.controllers.grid.users.author.PKPAuthorGridCellProvider');
import('lib.pkp.controllers.grid.users.author.AuthorGridRow');

// Link action & modal classes
import('lib.pkp.classes.linkAction.request.AjaxModal');

class AuthorGridHandler extends GridHandler {
	/** @var boolean */
	var $_readOnly;

	/** @var int */
	var $_version;

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
				array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR),
				array('fetchGrid', 'fetchRow', 'addAuthor', 'editAuthor',
				'updateAuthor', 'deleteAuthor', 'saveSequence'));
		$this->addRoleAssignment(ROLE_ID_REVIEWER, array('fetchGrid', 'fetchRow'));
		$this->addRoleAssignment(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT), array('addUser'));
	}


	//
	// Getters/Setters
	//
	/**
	 * Get the submission associated with this author grid.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}

	/**
	 * Get the publication associated with this author grid.
	 * @return Submission
	 */
	function getPublication() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_PUBLICATION);
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
		import('lib.pkp.classes.security.authorization.PublicationAccessPolicy');
		$this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc GridHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);

		$this->setTitle('submission.contributors');

		// Load pkp-lib translations
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_APP_DEFAULT,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_DEFAULT
		);

		if ($this->getSubmission()->getData('submissionProgress') || $this->canAdminister($request->getUser())) {
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
		$cellProvider = new PKPAuthorGridCellProvider($this->getPublication());
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
		if ($this->canAdminister($request->getUser())) {
			import('lib.pkp.classes.controllers.grid.feature.OrderGridItemsFeature');
			$features[] = new OrderGridItemsFeature();
		}
		return $features;
	}

	/**
	 * @copydoc GridHandler::getDataElementSequence()
	 */
	function getDataElementSequence($gridDataElement) {
		return $gridDataElement->getSequence();
	}

	/**
	 * @copydoc GridHandler::setDataElementSequence()
	 */
	function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence) {
		$author = DAORegistry::getDAO('AuthorDAO')->getById($rowId);
		$author->setSequence($newSequence);
		DAORegistry::getDAO('AuthorDAO')->updateObject($author);
	}

	/**
	 * @copydoc GridHandler::getRowInstance()
	 * @return AuthorGridRow
	 */
	protected function getRowInstance() {
		return new AuthorGridRow($this->getSubmission(), $this->getPublication(), $this->getReadOnly());
	}

	/**
	 * Get the arguments that will identify the data in the grid.
	 * Overridden by child grids.
	 * @return array
	 */
	function getRequestArgs() {
		$submission = $this->getSubmission();
		$publication = $this->getPublication();
		return array(
			'submissionId' => $submission->getId(),
			'publicationId' => $publication->getId()
		);
	}

	/**
	 * Determines if there should be add/edit actions on this grid.
	 * @param $user User
	 * @return boolean
	 */
	function canAdminister($user) {
		$publication = $this->getPublication();
		$submission = $this->getSubmission();
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

		if ($publication->getData('status') === STATUS_PUBLISHED) {
			return false;
		}

		if (in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
			return true;
		}

		// Incomplete submissions can be edited. (Presumably author.)
		if ($submission->getDateSubmitted() == null) return true;

		// The user may not be allowed to edit the metadata
		if (Services::get('submission')->canUserEditMetadata($submission->getId(), $user->getId())) {
			return true;
		}

		// Default: Read-only.
		return false;
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter = null) {
		return DAORegistry::getDAO('AuthorDAO')->getByPublicationId($this->getPublication()->getId(), true, false);
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
	 * Edit an author
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editAuthor($args, $request) {
		// Identify the author to be updated
		$authorId = (int) $request->getUserVar('authorId');

		$author = DAORegistry::getDAO('AuthorDAO')->getById($authorId);

		// Form handling
		import('controllers.grid.users.author.form.AuthorForm');
		$authorForm = new AuthorForm($this->getPublication(), $author);
		$authorForm->initData();

		return new JSONMessage(true, $authorForm->fetch($request));
	}

	/**
	 * Update an author
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateAuthor($args, $request) {
		// Identify the author to be updated
		$authorId = (int) $request->getUserVar('authorId');
		$publication = $this->getPublication();

		$author = Services::get('author')->get($authorId);

		// Form handling
		import('controllers.grid.users.author.form.AuthorForm');
		$authorForm = new AuthorForm($publication, $author);
		$authorForm->readInputData();
		if ($authorForm->validate()) {
			$authorId = $authorForm->execute();

			if(!isset($author)) {
				// This is a new contributor
				$author = Services::get('author')->get($authorId);
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
				$json = DAO::getDataChangedEvent();
			} else {
				$json = DAO::getDataChangedEvent($authorId);
			}
			$json->setGlobalEvent('authorsUpdated');
			return $json;
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
		if (!$request->checkCSRF()) return new JSONMessage(false);

		$authorId = (int) $request->getUserVar('authorId');

		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$authorDao->deleteById($authorId);
		$json = DAO::getDataChangedEvent($authorId);
		$json->setGlobalEvent('authorsUpdated');
		return $json;
	}

	/**
	 * Add a user with data initialized from an existing author.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function addUser($args, $request) {
		// Identify the author Id.
		$authorId = (int) $request->getUserVar('authorId');

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
			$userForm->initData();

			return new JSONMessage(true, $userForm->display($request));
		}
	}
}
