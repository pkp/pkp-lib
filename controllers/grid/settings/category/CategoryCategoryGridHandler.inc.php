<?php

/**
 * @file controllers/grid/settings/category/CategoryCategoryGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoryCategoryGridHandler
 * @ingroup controllers_grid_settings_category
 *
 * @brief Handle operations for category management operations.
 */

// Import user group grid specific classes
import('lib.pkp.controllers.grid.settings.category.CategoryGridCategoryRow');

use APP\category\Category;
use APP\facades\Repo;
use PKP\controllers\grid\CategoryGridHandler;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\feature\OrderCategoryGridItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\core\JSONMessage;
use PKP\facades\Locale;
use PKP\file\TemporaryFileManager;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class CategoryCategoryGridHandler extends CategoryGridHandler
{
    public $_contextId;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            [
                'fetchGrid',
                'fetchCategory',
                'fetchRow',
                'addCategory',
                'editCategory',
                'updateCategory',
                'deleteCategory',
                'uploadImage',
                'saveSequence',
            ]
        );
    }

    //
    // Overridden methods from PKPHandler.
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }


    /**
     * @copydoc CategoryGridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $context = $request->getContext();
        $this->_contextId = $context->getId();

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
        $cellProvider->setLocale(Locale::getLocale());

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
    public function loadData($request, $filter)
    {
        // For top-level rows, only list categories without parents.
        return iterator_to_array(Repo::category()->getMany(
            Repo::category()->getCollector()
                ->filterByContextIds([$this->_getContextId()])
                ->filterByParentIds([null])
        ));
    }

    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return array_merge(
            parent::initFeatures($request, $args),
            [new OrderCategoryGridItemsFeature(OrderCategoryGridItemsFeature::ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS, true, $this)]
        );
    }

    /**
     * @copydoc CategoryGridHandler::getDataElementInCategorySequence()
     */
    public function getDataElementInCategorySequence($categoryId, &$category)
    {
        return $category->getSequence();
    }

    /**
     * @copydoc CategoryGridHandler::setDataElementInCategorySequence()
     */
    public function setDataElementInCategorySequence($parentCategoryId, &$category, $newSequence)
    {
        $category->setSequence($newSequence);
        Repo::category()->edit($category, []);
    }

    /**
     * @copydoc GridHandler::getDataElementSequence()
     */
    public function getDataElementSequence($gridDataElement)
    {
        return $gridDataElement->getSequence();
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementSequence($request, $categoryId, $category, $newSequence)
    {
        $category->setSequence($newSequence);
        Repo::category()->edit($category, []);
    }

    /**
     * @copydoc CategoryGridHandler::getCategoryRowIdParameterName()
     */
    public function getCategoryRowIdParameterName()
    {
        return 'parentCategoryId';
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     */
    public function getRowInstance()
    {
        import('lib.pkp.controllers.grid.settings.category.CategoryGridRow');
        return new CategoryGridRow();
    }

    /**
     * @copydoc CategoryGridHandler::getCategoryRowInstance()
     */
    public function getCategoryRowInstance()
    {
        return new CategoryGridCategoryRow();
    }

    /**
     * @copydoc CategoryGridHandler::loadCategoryData()
     *
     * @param null|mixed $filter
     */
    public function loadCategoryData($request, &$category, $filter = null)
    {
        $categoryId = $category->getId();
        return iterator_to_array(Repo::category()->getMany(
            Repo::category()->getCollector()
                ->filterByContextIds([$this->_getContextId()])
                ->filterByParentIds([$categoryId])
        ));
    }

    /**
     * Handle the add category operation.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function addCategory($args, $request)
    {
        return $this->editCategory($args, $request);
    }

    /**
     * Handle the edit category operation.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editCategory($args, $request)
    {
        $categoryForm = $this->_getCategoryForm($request);

        $categoryForm->initData();

        return new JSONMessage(true, $categoryForm->fetch($request));
    }

    /**
     * Update category data in database and grid.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateCategory($args, $request)
    {
        $categoryForm = $this->_getCategoryForm($request);

        $categoryForm->readInputData();
        if ($categoryForm->validate()) {
            $categoryForm->execute();
            return \PKP\db\DAO::getDataChangedEvent();
        } else {
            return new JSONMessage(true, $categoryForm->fetch($request));
        }
    }

    /**
     * Delete a category
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteCategory($args, $request)
    {
        $context = $request->getContext();
        $category = Repo::category()->get((int) $request->getUserVar('categoryId'));
        if ($category && $category->getContextId() == $context->getId()) {
            Repo::category()->delete($category);
        }

        // FIXME delete dependent objects?

        return \PKP\db\DAO::getDataChangedEvent();
    }

    /**
     * Handle file uploads for cover/image art for things like Series and Categories.
     *
     * @param PKPRequest $request
     * @param array $args
     *
     * @return JSONMessage JSON object
     */
    public function uploadImage($args, $request)
    {
        $user = $request->getUser();

        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
        if ($temporaryFile) {
            $json = new JSONMessage(true);
            $json->setAdditionalAttributes([
                'temporaryFileId' => $temporaryFile->getId()
            ]);
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
     *
     * @param Request $request
     *
     * @return UserGroupForm
     */
    public function _getCategoryForm($request)
    {
        // Get the category ID.
        $categoryId = (int) $request->getUserVar('categoryId');

        // Instantiate the files form.
        import('lib.pkp.controllers.grid.settings.category.form.CategoryForm');
        $contextId = $this->_getContextId();
        return new CategoryForm($contextId, $categoryId);
    }

    /**
     * Get context id.
     *
     * @return int
     */
    public function _getContextId()
    {
        return $this->_contextId;
    }
}
