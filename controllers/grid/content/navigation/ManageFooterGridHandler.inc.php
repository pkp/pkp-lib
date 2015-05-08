<?php

/**
 * @file controllers/grid/content/navigation/ManageFooterGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FooterGridHandler
 * @ingroup controllers_grid_content_navigation
 *
 * @brief Handle manager requests for Footer navigation items.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.CategoryGridHandler');


// import format grid specific classes
import('lib.pkp.controllers.grid.content.navigation.FooterGridCellProvider');
import('lib.pkp.controllers.grid.content.navigation.FooterGridCategoryRow');
import('lib.pkp.controllers.grid.content.navigation.form.FooterCategoryForm');

// Link action & modal classes
import('lib.pkp.classes.linkAction.request.AjaxModal');

class ManageFooterGridHandler extends CategoryGridHandler {
	/**
	 * @var Context
	 */
	var $_context;

	/**
	 * Constructor
	 */
	function ManageFooterGridHandler() {
		parent::CategoryGridHandler();
		$this->addRoleAssignment(
				array(ROLE_ID_MANAGER),
				array('fetchGrid', 'fetchCategory', 'fetchRow', 'addFooterCategory',
				'editFooterCategory', 'updateFooterCategory', 'deleteFooterCategory'));
	}

	//
	// Getters/Setters
	//
	/**
	 * Get the context associated with this grid.
	 * @return Context
	 */
	function getContext() {
		return $this->_context;
	}

	/**
	 * Set the Context (authorized)
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
		$returner = parent::authorize($request, $args, $roleAssignments);

		$footerLinkId = $request->getUserVar('footerLinkId');
		if ($footerLinkId) {
			$context = $request->getContext();
			$footerLinkDao = DAORegistry::getDAO('FooterLinkDAO');
			$footerLink = $footerLinkDao->getById($footerLinkId, $context->getId());
			if (!isset($footerLink)) {
				return false;
			}
		}

		return $returner;
	}

	/*
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_MANAGER);

		// Basic grid configuration
		$this->setTitle('grid.content.navigation.footer');

		// Set the no items row text
		$this->setEmptyRowText('grid.content.navigation.footer.noneExist');

		$context = $request->getContext();
		$this->setContext($context);

		// Columns
		import('lib.pkp.controllers.grid.content.navigation.FooterGridCellProvider');
		$footerLinksGridCellProvider = new FooterGridCellProvider();

		$gridColumn = new GridColumn(
				'title',
				'common.title',
				null,
				null,
				$footerLinksGridCellProvider,
				array()
			);

		$gridColumn->addFlag('html', true);
		$this->addColumn($gridColumn);

		// Add grid action.
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$this->addAction(
			new LinkAction(
				'addFooterCategoryLink',
				new AjaxModal(
					$router->url($request, null, null, 'addFooterCategory', null, null),
					__('grid.content.navigation.footer.addCategory'),
					'modal_add_item',
					true
				),
				__('grid.content.navigation.footer.addCategory'),
				'add_item')
		);
	}


	//
	// Overridden methods from GridHandler
	//

	/**
	 * @copydoc CategoryGridHandler::getCategoryRowInstance()
	 * @return FooterGridCategoryRow
	 */
	protected function getCategoryRowInstance() {
		return new FooterGridCategoryRow();
	}

	/**
	 * @copydoc CategoryGridHandler::loadCategoryData()
	 */
	function loadCategoryData($request, $category) {

		$footerLinkDao = DAORegistry::getDAO('FooterLinkDAO');
		$context = $this->getContext();
		$footerLinks = $footerLinkDao->getByCategoryId($category->getId(), $context->getId());
		return $footerLinks->toArray();
	}

	/**
	 * @copydoc CategoryGridHandler::getCategoryRowIdParameterName()
	 */
	function getCategoryRowIdParameterName() {
		return 'footerCategoryId';
	}

	/**
	 * Get the arguments that will identify the data in the grid
	 * In this case, the context.
	 * @return array
	 */
	function getRequestArgs() {
		$context = $this->getContext();
		return array_merge(
			parent::getRequestArgs(),
			array('contextId' => $context->getId())
		);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter = null) {
		// set our labels for the FooterLink categories.
		$footerCategoryDao = DAORegistry::getDAO('FooterCategoryDAO');
		$context = $this->getContext();
		$categories = $footerCategoryDao->getByContextId($context->getId());
		$data = array();
		while ($category = $categories->next()) {
			$data[ $category->getId() ] = $category;
		}

		return $data;
	}


	//
	// Public Footer Grid Actions
	//

	/**
	 * Add a footer category entry.  This simply calls editFooterCategory().
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function addFooterCategory($args, $request) {
		return $this->editFooterCategory($args, $request);
	}

	/**
	 * Edit a footer category entry
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editFooterCategory($args, $request) {
		$footerCategoryId = $request->getUserVar('footerCategoryId');
		$footerCategoryDao = DAORegistry::getDAO('FooterCategoryDAO');
		$context = $request->getContext();

		$footerCategory = $footerCategoryDao->getById($footerCategoryId, $context->getId());
		$footerCategoryForm = new FooterCategoryForm($context->getId(), $footerCategory);
		$footerCategoryForm->initData($args, $request);

		return new JSONMessage(true, $footerCategoryForm->fetch($request));
	}

	/**
	 * Update a footer category entry
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateFooterCategory($args, $request) {
		// Identify the footerLink entry to be updated
		$footerCategoryId = $request->getUserVar('footerCategoryId');

		$context = $this->getContext();

		$footerCategoryDao = DAORegistry::getDAO('FooterCategoryDAO');
		$footerCategory = $footerCategoryDao->getById($footerCategoryId, $context->getId());

		// Form handling
		$footerCategoryForm = new FooterCategoryForm($context->getId(), $footerCategory);
		$footerCategoryForm->readInputData();
		if ($footerCategoryForm->validate()) {
			$footerCategoryId = $footerCategoryForm->execute($request);

			if(!isset($footerCategory)) {
				// This is a new entry
				$footerCategory = $footerCategoryDao->getById($footerCategoryId, $context->getId());
				$notificationContent = __('notification.addedFooterCategory');

				// Prepare the grid row data
				$row = $this->getRowInstance();
				$row->setGridId($this->getId());
				$row->setId($footerCategoryId);
				$row->setData($footerCategory);
				$row->initialize($request);
			} else {
				$notificationContent = __('notification.editedFooterCategory');
			}

			// Create trivial notification.
			$currentUser = $request->getUser();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => $notificationContent));

			// Render the row into a JSON response
			return DAO::getDataChangedEvent($footerCategoryId);

		} else {
			return new JSONMessage(true, $footerCategoryForm->fetch($request));
		}
	}

	/**
	 * Delete a footer category entry
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteFooterCategory($args, $request) {

		// Identify the entry to be deleted
		$footerCategoryId = $request->getUserVar('footerCategoryId');

		$footerCategoryDao = DAORegistry::getDAO('FooterCategoryDAO');
		$context = $this->getContext();
		$footerCategory = $footerCategoryDao->getById($footerCategoryId, $context->getId());
		if (isset($footerCategory)) { // authorized

			// remove links in this category.
			$footerLinkDao = DAORegistry::getDAO('FooterLinkDAO');
			$footerLinks = $footerLinkDao->getByCategoryId($footerCategoryId, $context->getId());
			while ($footerLink = $footerLinks->next()) {
				$footerLinkDao->deleteObject($footerLink);
			}

			$result = $footerCategoryDao->deleteObject($footerCategory);

			if ($result) {
				$currentUser = $request->getUser();
				$notificationMgr = new NotificationManager();
				$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.removedFooterCategory')));
				return DAO::getDataChangedEvent($footerCategoryId);
			} else {
				return new JSONMessage(false, __('manager.setup.errorDeletingItem'));
			}
		}
	}
}
?>
