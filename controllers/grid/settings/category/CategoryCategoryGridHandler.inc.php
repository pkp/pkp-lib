<?php

/**
 * @file controllers/grid/settings/category/CategoryCategoryGridHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CategoryCategoryGridHandler
 * @ingroup controllers_grid_settings_category
 *
 * @brief Handle operations for category management operations.
 */

// Import the base GridHandler.
import('lib.pkp.classes.controllers.grid.CategoryGridHandler');
import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

// Import user group grid specific classes
import('lib.pkp.controllers.grid.settings.category.CategoryGridCategoryRow');

// Link action & modal classes
import('lib.pkp.classes.linkAction.request.AjaxModal');

class CategoryCategoryGridHandler extends CategoryGridHandler {
	var $_contextId;

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN),
			array(
				'fetchGrid',
				'fetchCategory',
				'fetchRow',
				'addCategory',
				'editCategory',
				'updateCategory',
				'deleteCategory',
				'uploadImage',
				'saveSequence',
			)
		);
	}

	//
	// Overridden methods from PKPHandler.
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}


	/**
	 * @copydoc CategoryGridHandler::initialize()
	 */
	function initialize($request, $args = null) {

		parent::initialize($request, $args);

		$context = $request->getContext();
		$this->_contextId = $context->getId();

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_SUBMISSION);

		// Set the grid title.
		$this->setTitle('grid.category.categories');

		// Add grid-level actions.
		$router = $request->getRouter();
		$this->addAction(
			new LinkAction(
				'addCategory',
				new AjaxModal(
					$router->url($request, null, null, 'addCategory'),
					__('grid.category.add'),
					'modal_manage'
				),
				__('grid.category.add'),
				'add_category'
			)
		);

		// Add grid columns.
		$cellProvider = new DataObjectGridCellProvider();
		$cellProvider->setLocale(AppLocale::getLocale());

		$this->addColumn(
			new GridColumn(
				'title',
				'grid.category.name',
				null,
				null,
				$cellProvider
			)
		);
	}

	/**
	 * @copydoc GridHandler::loadData
	 */
	function loadData($request, $filter) {
		// For top-level rows, only list categories without parents.
		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		$categoriesIterator = $categoryDao->getByParentId(null, $this->_getContextId());
		return $categoriesIterator->toAssociativeArray();
	}

	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.OrderCategoryGridItemsFeature');
		return array_merge(
			parent::initFeatures($request, $args),
			array(new OrderCategoryGridItemsFeature(ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS))
		);
	}

	/**
	 * @copydoc CategoryGridHandler::getDataElementInCategorySequence()
	 */
	function getDataElementInCategorySequence($categoryId, &$category) {
		return $category->getSequence();
	}

	/**
	 * @copydoc CategoryGridHandler::setDataElementInCategorySequence()
	 */
	function setDataElementInCategorySequence($parentCategoryId, &$category, $newSequence) {
		$category->setSequence($newSequence);
		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		$categoryDao->updateObject($category);
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
	function setDataElementSequence($request, $categoryId, $category, $newSequence) {
		$category->setSequence($newSequence);
		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		$categoryDao->updateObject($category);
	}

	/**
	 * @copydoc CategoryGridHandler::getCategoryRowIdParameterName()
	 */
	function getCategoryRowIdParameterName() {
		return 'parentCategoryId';
	}

	/**
	 * @copydoc GridHandler::getRowInstance()
	 */
	function getRowInstance() {
		import('lib.pkp.controllers.grid.settings.category.CategoryGridRow');
		return new CategoryGridRow();
	}

	/**
	 * @copydoc CategoryGridHandler::getCategoryRowInstance()
	 */
	function getCategoryRowInstance() {
		return new CategoryGridCategoryRow();
	}

	/**
	 * @copydoc CategoryGridHandler::loadCategoryData()
	 */
	function loadCategoryData($request, &$category, $filter = null) {
		$categoryId = $category->getId();
		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		$categoriesIterator = $categoryDao->getByParentId($categoryId, $this->_getContextId());
		return $categoriesIterator->toAssociativeArray();
	}

	/**
	 * Handle the add category operation.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function addCategory($args, $request) {
		return $this->editCategory($args, $request);
	}

	/**
	 * Handle the edit category operation.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editCategory($args, $request) {
		$categoryForm = $this->_getCategoryForm($request);

		$categoryForm->initData();

		return new JSONMessage(true, $categoryForm->fetch($request));
	}

	/**
	 * Update category data in database and grid.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateCategory($args, $request) {
		$categoryForm = $this->_getCategoryForm($request);

		$categoryForm->readInputData();
		if($categoryForm->validate()) {
			$categoryForm->execute();
			return DAO::getDataChangedEvent();
		} else {
			return new JSONMessage(true, $categoryForm->fetch($request));
		}
	}

	/**
	 * Delete a category
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteCategory($args, $request) {
		// Identify the category to be deleted
		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		$context = $request->getContext();
		$category = $categoryDao->getById(
			$request->getUserVar('categoryId'),
			$context->getId()
		);

		// FIXME delete dependent objects?

		// Delete the category
		$categoryDao->deleteObject($category);
		return DAO::getDataChangedEvent();
	}

	/**
	 * Handle file uploads for cover/image art for things like Series and Categories.
	 * @param $request PKPRequest
	 * @param $args array
	 * @return JSONMessage JSON object
	 */
	function uploadImage($args, $request) {
		$user = $request->getUser();

		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
		if ($temporaryFile) {
			$json = new JSONMessage(true);
			$json->setAdditionalAttributes(array(
					'temporaryFileId' => $temporaryFile->getId()
			));
			return $json;
		} else {
			return new JSONMessage(false, __('common.uploadFailed'));
		}
	}

	//
	// Private helper methods.
	//
	/**
	 * Get a CategoryForm instance.
	 * @param $request Request
	 * @return UserGroupForm
	 */
	function _getCategoryForm($request) {
		// Get the category ID.
		$categoryId = (int) $request->getUserVar('categoryId');

		// Instantiate the files form.
		import('lib.pkp.controllers.grid.settings.category.form.CategoryForm');
		$contextId = $this->_getContextId();
		return new CategoryForm($contextId, $categoryId);
	}

	/**
	 * Get context id.
	 * @return int
	 */
	function _getContextId() {
		return $this->_contextId;
	}
}


