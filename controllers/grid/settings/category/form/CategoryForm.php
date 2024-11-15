<?php

/**
 * @file lib/pkp/controllers/grid/settings/category/form/CategoryForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoryForm
 *
 * @ingroup controllers_grid_settings_category_form
 *
 * @brief Form to add/edit category.
 */

namespace PKP\controllers\grid\settings\category\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\context\SubEditorsDAO;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\file\ContextFileManager;
use PKP\file\TemporaryFileDAO;
use PKP\file\TemporaryFileManager;
use PKP\form\Form;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class CategoryForm extends Form
{
    /** @var int Id of the category being edited */
    public $_categoryId;

    /** @var int The context ID of the category being edited */
    public $_contextId;

    /** @var int $_userId The current user ID */
    public $_userId;

    /** @var string $_imageExtension Cover image extension */
    public $_imageExtension;

    /** @var array $_sizeArray Cover image information from getimagesize */
    public $_sizeArray;

    /** @var array Roles that can be assigned to this category */
    public $assignableRoles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];


    /**
     * Constructor.
     *
     * @param int $contextId Context id.
     * @param int $categoryId Category id.
     */
    public function __construct(int $contextId, $categoryId = null)
    {
        parent::__construct('controllers/grid/settings/category/form/categoryForm.tpl');
        $this->_contextId = $contextId;
        $this->_categoryId = $categoryId;

        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $this->_userId = $user->getId();

        // Validation checks for this form
        $form = $this;
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'name', 'required', 'grid.category.nameRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorRegExp($this, 'path', 'required', 'grid.category.pathAlphaNumeric', '/^[a-zA-Z0-9\/._-]+$/'));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom(
            $this,
            'path',
            'required',
            'grid.category.pathExists',
            function ($path) use ($form, $contextId) {
                $category = Repo::category()->getCollector()
                    ->filterByContextIds([$contextId])
                    ->filterByPaths([$path])
                    ->getMany()
                    ->first();

                return !$category || $category->getPath() == $form->getData('oldPath');
            }
        ));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    //
    // Getters and Setters
    //
    /**
     * Get the category id.
     *
     * @return int categoryId
     */
    public function getCategoryId()
    {
        return $this->_categoryId;
    }

    /**
     * Set the category ID for this section.
     *
     * @param int $categoryId
     */
    public function setCategoryId($categoryId)
    {
        $this->_categoryId = $categoryId;
    }

    /**
     * Get the context id.
     *
     * @return int contextId
     */
    public function getContextId()
    {
        return $this->_contextId;
    }

    //
    // Implement template methods from Form.
    //
    /**
     * Get all locale field names
     */
    public function getLocaleFieldNames(): array
    {
        return ['name', 'description'];
    }

    /**
     * @see Form::initData()
     */
    public function initData()
    {
        $category = Repo::category()->get($this->getCategoryId());

        $this->setData('assignedSubeditors', []);

        if ($category) {
            if ($category->getContextId() != $this->getContextId()) {
                throw new \Exception('Wrong context ID for category!');
            }

            $this->setData('name', $category->getTitle(null)); // Localized
            $this->setData('description', $category->getDescription(null)); // Localized
            $this->setData('parentId', $category->getParentId());
            $this->setData('path', $category->getPath());
            $this->setData('image', $category->getImage());

            $sortOption = $category->getSortOption() ? $category->getSortOption() : Repo::submission()->getDefaultSortOption();
            $this->setData('sortOption', $sortOption);

            $subeditorUserGroups = [];
            $assignedSubeditors = Repo::user()
                ->getCollector()
                ->filterByContextIds([Application::get()->getRequest()->getContext()->getId()])
                ->filterByRoleIds($this->assignableRoles)
                ->assignedToCategoryIds([$this->getCategoryId()])
                ->getIds()
                ->toArray();

            if (!empty($assignedSubeditors)) {
                $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO'); /** @var SubEditorsDAO $subEditorsDao */
                $subeditorUserGroups = $subEditorsDao->getAssignedUserGroupIds(
                    Application::get()->getRequest()->getContext()->getId(),
                    Application::ASSOC_TYPE_CATEGORY,
                    $this->getCategoryId(),
                    $assignedSubeditors
                )->toArray();
            }

            $this->setData('subeditorUserGroups', $subeditorUserGroups);
        }

        return parent::initData();
    }

    /**
     * @see Form::validate()
     */
    public function validate($callHooks = true)
    {
        if ($temporaryFileId = $this->getData('temporaryFileId')) {
            $temporaryFileManager = new TemporaryFileManager();
            $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */
            $temporaryFile = $temporaryFileDao->getTemporaryFile($temporaryFileId, $this->_userId);
            if (!$temporaryFile ||
                !($this->_imageExtension = $temporaryFileManager->getImageExtension($temporaryFile->getFileType())) ||
                !($this->_sizeArray = getimagesize($temporaryFile->getFilePath())) ||
                $this->_sizeArray[0] <= 0 || $this->_sizeArray[1] <= 0
            ) {
                $this->addError('temporaryFileId', __('form.invalidImage'));
                return false;
            }
        }
        return parent::validate($callHooks);
    }

    /**
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['name', 'parentId', 'path', 'description', 'temporaryFileId', 'sortOption', 'subEditors']);

        // For path duplicate checking; excuse the current path.
        if ($categoryId = $this->getCategoryId()) {
            $category = Repo::category()->get($categoryId);
            if ($category->getContextId() != $this->getContextId()) {
                throw new \Exception('Wrong context ID for category!');
            }
            $this->setData('oldPath', $category->getPath());
        }
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $context = $request->getContext();
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('categoryId', $this->getCategoryId());

        // Provide a list of root categories to the template
        $rootCategoriesCollection = Repo::category()->getCollector()
            ->filterByParentIds([null])
            ->filterByContextIds([$context->getId()])
            ->getMany();

        $rootCategories = [null => __('common.none')];
        foreach ($rootCategoriesCollection as $category) {
            $categoryId = $category->getId();
            if ($categoryId != $this->getCategoryId()) {
                // Don't permit time travel paradox
                $rootCategories[$categoryId] = $category->getLocalizedTitle();
            }
        }
        $templateMgr->assign('rootCategories', $rootCategories);

        // Determine if this category has children of its own;
        // if so, prevent the user from giving it a parent.
        // (Forced two-level maximum tree depth.)
        if ($this->getCategoryId()) {
            $childCount = Repo::category()->getCollector()
                ->filterByParentIds([$this->getCategoryId()])
                ->filterByContextIds([$context->getId()])
                ->getCount();

            $templateMgr->assign('cannotSelectChild', $childCount > 0);
        }
        // Sort options.
        $templateMgr->assign('sortOptions', Repo::submission()->getSortSelectOptions());

        $assignableUserGroups = UserGroup::query()
            ->withContextIds([$request->getContext()->getId()])
            ->withRoleIds($this->assignableRoles)
            ->withStageIds([WORKFLOW_STAGE_ID_SUBMISSION])
            ->get()
            ->map(function (UserGroup $userGroup) use ($request) {
                return [
                    'userGroup' => $userGroup,
                    'users' => Repo::user()
                        ->getCollector()
                        ->filterByUserGroupIds([$userGroup->id])
                        ->filterByContextIds([$request->getContext()->getId()])
                        ->getMany()
                        ->mapWithKeys(fn ($user, $key) => [$user->getId() => $user->getFullName()])
                        ->toArray()
                ];
            });

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'assignableUserGroups' => $assignableUserGroups->toArray(),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $categoryId = $this->getCategoryId();
        $context = Application::get()->getRequest()->getContext();

        // Get a category object to edit or create
        if ($categoryId == null) {
            $category = Repo::category()->dao->newDataObject();
            $category->setContextId($this->getContextId());
        } else {
            $category = Repo::category()->get($categoryId);
            if ($category->getContextId() != $this->getContextId()) {
                throw new \Exception('Wrong context ID for category!');
            }
        }

        // Set the editable properties of the category object
        $category->setTitle($this->getData('name'), null); // Localized
        $category->setDescription($this->getData('description'), null); // Localized
        $category->setParentId(((int) $this->getData('parentId')) ?: null);
        $category->setPath($this->getData('path'));
        $category->setSortOption($this->getData('sortOption'));

        // Update or insert the category object
        if ($categoryId == null) {
            $this->setCategoryId(Repo::category()->add($category));
            $category->setSequence(REALLY_BIG_NUMBER);
            Repo::category()->dao->resequenceCategories($this->getContextId());
        } else {
            Repo::category()->edit($category, []);
        }

        // Update category editors
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO'); /** @var SubEditorsDAO $subEditorsDao */
        $subEditorsDao->deleteBySubmissionGroupId($category->getId(), Application::ASSOC_TYPE_CATEGORY, $category->getContextId());
        $subEditors = $this->getData('subEditors');
        if (!empty($subEditors)) {
            $allowedEditors = Repo::user()
                ->getCollector()
                ->filterByRoleIds($this->assignableRoles)
                ->filterByContextIds([$context->getId()])
                ->getIds();
            foreach ($subEditors as $userGroupId => $userIds) {
                foreach ($userIds as $userId) {
                    if (!$allowedEditors->contains($userId)) {
                        continue;
                    }
                    $subEditorsDao->insertEditor($context->getId(), $this->getCategoryId(), $userId, Application::ASSOC_TYPE_CATEGORY, (int) $userGroupId);
                }
            }
        }

        // Handle the image upload if there was one.
        if ($temporaryFileId = $this->getData('temporaryFileId')) {
            // Fetch the temporary file storing the uploaded library file
            $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */

            $temporaryFile = $temporaryFileDao->getTemporaryFile($temporaryFileId, $this->_userId);
            $temporaryFilePath = $temporaryFile->getFilePath();
            $contextFileManager = new ContextFileManager($this->getContextId());
            $basePath = $contextFileManager->getBasePath() . '/categories/';

            // Delete the old file if it exists
            $oldSetting = $category->getImage();
            if ($oldSetting) {
                $contextFileManager->deleteByPath($basePath . $oldSetting['thumbnailName']);
                $contextFileManager->deleteByPath($basePath . $oldSetting['name']);
            }

            // The following variables were fetched in validation
            assert($this->_sizeArray && $this->_imageExtension);

            // Generate the surrogate images.
            switch ($this->_imageExtension) {
                case '.jpg': $image = imagecreatefromjpeg($temporaryFilePath);
                    break;
                case '.png': $image = imagecreatefrompng($temporaryFilePath);
                    break;
                case '.gif': $image = imagecreatefromgif($temporaryFilePath);
                    break;
                default: $image = null; // Suppress warning
            }
            assert($image);

            $context = Application::get()->getRequest()->getContext();
            $coverThumbnailsMaxWidth = $context->getSetting('coverThumbnailsMaxWidth');
            $coverThumbnailsMaxHeight = $context->getSetting('coverThumbnailsMaxHeight');
            $thumbnailFilename = $category->getId() . '-category-thumbnail' . $this->_imageExtension;
            $xRatio = min(1, ($coverThumbnailsMaxWidth ? $coverThumbnailsMaxWidth : 100) / $this->_sizeArray[0]);
            $yRatio = min(1, ($coverThumbnailsMaxHeight ? $coverThumbnailsMaxHeight : 100) / $this->_sizeArray[1]);

            $ratio = min($xRatio, $yRatio);

            $thumbnailWidth = round($ratio * $this->_sizeArray[0]);
            $thumbnailHeight = round($ratio * $this->_sizeArray[1]);
            $thumbnail = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbnailWidth, $thumbnailHeight, $this->_sizeArray[0], $this->_sizeArray[1]);

            // Copy the new file over
            $filename = $category->getId() . '-category' . $this->_imageExtension;
            $contextFileManager->copyFile($temporaryFile->getFilePath(), $basePath . $filename);

            switch ($this->_imageExtension) {
                case '.jpg': imagejpeg($thumbnail, $basePath . $thumbnailFilename);
                    break;
                case '.png': imagepng($thumbnail, $basePath . $thumbnailFilename);
                    break;
                case '.gif': imagegif($thumbnail, $basePath . $thumbnailFilename);
                    break;
            }
            imagedestroy($thumbnail);
            imagedestroy($image);

            $category->setImage([
                'name' => $filename,
                'width' => $this->_sizeArray[0],
                'height' => $this->_sizeArray[1],
                'thumbnailName' => $thumbnailFilename,
                'thumbnailWidth' => $thumbnailWidth,
                'thumbnailHeight' => $thumbnailHeight,
                'uploadName' => $temporaryFile->getOriginalFileName(),
                'dateUploaded' => Core::getCurrentDate(),
            ]);

            // Clean up the temporary file
            $temporaryFileManager = new TemporaryFileManager();
            $temporaryFileManager->deleteById($temporaryFileId, $this->_userId);
        }

        // Update category object to store image information.
        Repo::category()->edit($category, []);
        parent::execute(...$functionArgs);
        return $category;
    }
}
