<?php

/**
 * @file controllers/grid/content/navigation/ManageSocialMediaGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ManageSocialMediaGridHandler
 * @ingroup controllers_grid_content_navigation
 *
 * @brief Handle social media grid requests.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');


// import grid specific classes
import('lib.pkp.controllers.grid.content.navigation.SocialMediaGridCellProvider');
import('lib.pkp.controllers.grid.content.navigation.SocialMediaGridRow');

// Link action & modal classes
import('lib.pkp.classes.linkAction.request.AjaxModal');

class ManageSocialMediaGridHandler extends GridHandler {
	/** @var Context */
	var $_context;

	/**
	 * Constructor
	 */
	function ManageSocialMediaGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array(
				'fetchGrid', 'fetchRow', 'addMedia',
				'editMedia', 'updateMedia', 'deleteMedia'
			)
		);
	}


	//
	// Getters/Setters
	//
	/**
	 * Get the context associated with this grid.
	 * @return Context
	 */
	function &getContext() {
		return $this->_context;
	}

	/**
	 * Set the Context
	 * @param Context
	 */
	function setContext($context) {
		$this->_context = $context;
	}


	//
	// Overridden methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PkpContextAccessPolicy');
		$this->addPolicy(new PkpContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/*
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		// Retrieve the authorized context.
		$this->setContext($request->getContext());

		// Load submission-specific translations
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_DEFAULT,
			LOCALE_COMPONENT_PKP_DEFAULT
		);

		// Basic grid configuration
		$this->setTitle('grid.content.navigation.socialMedia');

		// Grid actions
		$router = $request->getRouter();
		$actionArgs = $this->getRequestArgs();
		$this->addAction(
			new LinkAction(
				'addMedia',
				new AjaxModal(
					$router->url($request, null, null, 'addMedia', null, $actionArgs),
					__('grid.content.navigation.socialMedia.addSocialLink'),
					'modal_add_item'
				),
				__('grid.content.navigation.socialMedia.addSocialLink'),
				'add_item'
			)
		);

		// Columns
		$cellProvider = new SocialMediaGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'platform',
				'grid.content.navigation.socialMedia.platform',
				null,
				null,
				$cellProvider,
				array('width' => 50, 'alignment' => COLUMN_ALIGNMENT_LEFT)
			)
		);
		$this->addColumn(
			new GridColumn(
				'inCatalog',
				'grid.content.navigation.socialMedia.inCatalog',
				null,
				'controllers/grid/common/cell/checkMarkCell.tpl',
				$cellProvider
			)
		);
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @copydoc GridHandler::getRowInstance()
	 * @return SocialMediaGridRow
	 */
	protected function getRowInstance() {
		$context = $this->getContext();
		return new SocialMediaGridRow($context);
	}

	/**
	 * Get the arguments that will identify the data in the grid
	 * @return array
	 */
	function getRequestArgs() {
		$context = $this->getContext();
		return array(
			'contextId' => $context->getId()
		);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter = null) {
		$context = $this->getContext();
		$socialMediaDao = DAORegistry::getDAO('SocialMediaDAO');
		$data = $socialMediaDao->getByContextId($context->getId());
		return $data->toArray();
	}


	//
	// Public Grid Actions
	//

	function addMedia($args, $request) {
		return $this->editMedia($args, $request);
	}

	/**
	 * Edit a social media entry
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editMedia($args, $request) {
		// Identify the object to be updated
		$socialMediaId = (int) $request->getUserVar('socialMediaId');
		$context = $this->getContext();

		$socialMediaDao = DAORegistry::getDAO('SocialMediaDAO');
		$socialMedia = $socialMediaDao->getById($socialMediaId, $context->getId());

		// Form handling
		import('lib.pkp.controllers.grid.content.navigation.form.SocialMediaForm');
		$socialMediaForm = new SocialMediaForm($context->getId(), $socialMedia);
		$socialMediaForm->initData();

		return new JSONMessage(true, $socialMediaForm->fetch($request));
	}

	/**
	 * Edit a social media entry
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateMedia($args, $request) {
		// Identify the object to be updated
		$socialMediaId = (int) $request->getUserVar('socialMediaId');
		$context = $this->getContext();

		$socialMediaDao = DAORegistry::getDAO('SocialMediaDAO');
		$socialMedia = $socialMediaDao->getById($socialMediaId, $context->getId());

		// Form handling
		import('lib.pkp.controllers.grid.content.navigation.form.SocialMediaForm');
		$socialMediaForm = new SocialMediaForm($context->getId(), $socialMedia);
		$socialMediaForm->readInputData();
		if ($socialMediaForm->validate()) {
			$socialMediaId = $socialMediaForm->execute($request);

			if(!isset($socialMedia)) {
				// This is a new media object
				$socialMedia = $socialMediaDao->getById($socialMediaId, $context->getId());
				// New added media action notification content.
				$notificationContent = __('notification.addedSocialMedia');
			} else {
				// Media edit action notification content.
				$notificationContent = __('notification.editedSocialMedia');
			}

			// Create trivial notification.
			$currentUser = $request->getUser();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => $notificationContent));

			// Prepare the grid row data
			$row = $this->getRowInstance();
			$row->setGridId($this->getId());
			$row->setId($socialMediaId);
			$row->setData($socialMedia);
			$row->initialize($request);

			// Render the row into a JSON response
			return DAO::getDataChangedEvent();

		} else {
			return new JSONMessage(true, $socialMediaForm->fetch($request));
		}
	}

	/**
	 * Delete a media entry
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteMedia($args, $request) {

		// Identify the object to be deleted
		$socialMediaId = (int) $request->getUserVar('socialMediaId');

		$context = $this->getContext();

		$socialMediaDao = DAORegistry::getDAO('SocialMediaDAO');
		$socialMedia = $socialMediaDao->getById($socialMediaId, $context->getId());
		if (isset($socialMedia)) {
			$result = $socialMediaDao->deleteObject($socialMedia);
			if ($result) {
				$currentUser = $request->getUser();
				$notificationMgr = new NotificationManager();
				$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.removedSocialMedia')));
				return DAO::getDataChangedEvent();
			} else {
				return new JSONMessage(false, __('manager.setup.errorDeletingItem'));
			}
		} else {
			fatalError('Social Media not in current context context.');
		}
	}
}

?>
