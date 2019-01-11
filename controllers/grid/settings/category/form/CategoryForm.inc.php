<?php

/**
 * @file lib/pkp/controllers/grid/settings/category/form/CategoryForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CategoryForm
 * @ingroup controllers_grid_settings_category_form
 *
 * @brief Form to add/edit category.
 */

import('lib.pkp.classes.form.Form');

class CategoryForm extends Form {
	/** @var Id of the category being edited */
	var $_categoryId;

	/** @var The context ID of the category being edited */
	var $_contextId;

	/** @var $_userId int The current user ID */
	var $_userId;

	/** @var $_imageExtension string Cover image extension */
	var $_imageExtension;

	/** @var $_sizeArray array Cover image information from getimagesize */
	var $_sizeArray;


	/**
	 * Constructor.
	 * @param $contextId Context id.
	 * @param $categoryId Category id.
	 */
	function __construct($contextId, $categoryId = null) {
		parent::__construct('controllers/grid/settings/category/form/categoryForm.tpl');
		$this->_contextId = $contextId;
		$this->_categoryId = $categoryId;

		$request = Application::getRequest();
		$user = $request->getUser();
		$this->_userId = $user->getId();

		// Validation checks for this form
		$form = $this;
		$this->addCheck(new FormValidatorLocale($this, 'name', 'required', 'grid.category.nameRequired'));
		$this->addCheck(new FormValidatorRegExp($this, 'path', 'required', 'grid.category.pathAlphaNumeric', '/^[a-zA-Z0-9\/._-]+$/'));
		$this->addCheck(new FormValidatorCustom(
			$this, 'path', 'required', 'grid.category.pathExists',
			function($path) use ($form, $contextId) {
				$categoryDao = DAORegistry::getDAO('CategoryDAO');
				return !$categoryDao->categoryExistsByPath($path,$contextId) || ($form->getData('oldPath') != null && $form->getData('oldPath') == $path);
			}
		));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the user group id.
	 * @return int categoryId
	 */
	function getCategoryId() {
		return $this->_categoryId;
	}

	/**
	 * Get the context id.
	 * @return int contextId
	 */
	function getContextId() {
		return $this->_contextId;
	}

	//
	// Implement template methods from Form.
	//
	/**
	 * Get all locale field names
	 */
	function getLocaleFieldNames() {
		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		return $categoryDao->getLocaleFieldNames();
	}

	/**
	 * @see Form::initData()
	 */
	function initData() {
		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		$category = $categoryDao->getById($this->getCategoryId(), $this->getContextId());

		if ($category) {
			$this->setData('name', $category->getTitle(null)); // Localized
			$this->setData('description', $category->getDescription(null)); // Localized
			$this->setData('parentId', $category->getParentId());
			$this->setData('path', $category->getPath());
			$this->setData('image', $category->getImage());

			$publishedSubmissionDao = Application::getSubmissionDAO();
			$sortOption = $category->getSortOption() ? $category->getSortOption() : $publishedSubmissionDao->getDefaultSortOption();
			$this->setData('sortOption', $sortOption);
		}
	}

	/**
	 * @see Form::validate()
	 */
	function validate($callHooks = true) {
		if ($temporaryFileId = $this->getData('temporaryFileId')) {
			import('lib.pkp.classes.file.TemporaryFileManager');
			$temporaryFileManager = new TemporaryFileManager();
			$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
			$temporaryFile = $temporaryFileDao->getTemporaryFile($temporaryFileId, $this->_userId);
			if (	!$temporaryFile ||
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
	function readInputData() {
		$this->readUserVars(array('name', 'parentId', 'path', 'description', 'temporaryFileId', 'sortOption'));

		// For path duplicate checking; excuse the current path.
		if ($categoryId = $this->getCategoryId()) {
			$categoryDao = DAORegistry::getDAO('CategoryDAO');
			$category = $categoryDao->getById($categoryId, $this->getContextId());
			$this->setData('oldPath', $category->getPath());
		}
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		$context = $request->getContext();
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('categoryId', $this->getCategoryId());

		// Provide a list of root categories to the template
		$rootCategoriesIterator = $categoryDao->getByParentId(0, $context->getId());
		$rootCategories = array(0 => __('common.none'));
		while ($category = $rootCategoriesIterator->next()) {
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
			$children = $categoryDao->getByParentId($this->getCategoryId(), $context->getId());
			if ($children->next()) {
				$templateMgr->assign('cannotSelectChild', true);
			}
		}
		// Sort options.
		$submissionDao = Application::getSubmissionDAO();
		$templateMgr->assign('sortOptions', $submissionDao->getSortSelectOptions());

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute() {
		$categoryId = $this->getCategoryId();
		$categoryDao = DAORegistry::getDAO('CategoryDAO');

		// Get a category object to edit or create
		if ($categoryId == null) {
			$category = $categoryDao->newDataObject();
			$category->setContextId($this->getContextId());
		} else {
			$category = $categoryDao->getById($categoryId, $this->getContextId());
		}

		// Set the editable properties of the category object
		$category->setTitle($this->getData('name'), null); // Localized
		$category->setDescription($this->getData('description'), null); // Localized
		$category->setParentId($this->getData('parentId'));
		$category->setPath($this->getData('path'));
		$category->setSortOption($this->getData('sortOption'));

		// Update or insert the category object
		if ($categoryId == null) {
			$category->setId($categoryDao->insertObject($category));
		} else {
			$category->setSequence(REALLY_BIG_NUMBER);
			$categoryDao->updateObject($category);
			$categoryDao->resequenceCategories($this->getContextId());
		}

		// Handle the image upload if there was one.
		if ($temporaryFileId = $this->getData('temporaryFileId')) {
			// Fetch the temporary file storing the uploaded library file
			$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');

			$temporaryFile = $temporaryFileDao->getTemporaryFile($temporaryFileId, $this->_userId);
			$temporaryFilePath = $temporaryFile->getFilePath();
			import('lib.pkp.classes.file.ContextFileManager');
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
				case '.jpg': $image = imagecreatefromjpeg($temporaryFilePath); break;
				case '.png': $image = imagecreatefrompng($temporaryFilePath); break;
				case '.gif': $image = imagecreatefromgif($temporaryFilePath); break;
				default: $image = null; // Suppress warning
			}
			assert($image);

			$context = Application::getRequest()->getContext();
			$coverThumbnailsMaxWidth = $context->getSetting('coverThumbnailsMaxWidth');
			$coverThumbnailsMaxHeight = $context->getSetting('coverThumbnailsMaxHeight');
			$thumbnailFilename = $category->getId() . '-category-thumbnail' . $this->_imageExtension;
			$xRatio = min(1, ($coverThumbnailsMaxWidth?$coverThumbnailsMaxWidth:100) / $this->_sizeArray[0]);
			$yRatio = min(1, ($coverThumbnailsMaxHeight?$coverThumbnailsMaxHeight:100) / $this->_sizeArray[1]);

			$ratio = min($xRatio, $yRatio);

			$thumbnailWidth = round($ratio * $this->_sizeArray[0]);
			$thumbnailHeight = round($ratio * $this->_sizeArray[1]);
			$thumbnail = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);
			imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbnailWidth, $thumbnailHeight, $this->_sizeArray[0], $this->_sizeArray[1]);

			// Copy the new file over
			$filename = $category->getId() . '-category' . $this->_imageExtension;
			$contextFileManager->copyFile($temporaryFile->getFilePath(), $basePath . $filename);

			switch ($this->_imageExtension) {
				case '.jpg': imagejpeg($thumbnail, $basePath . $thumbnailFilename); break;
				case '.png': imagepng($thumbnail, $basePath . $thumbnailFilename); break;
				case '.gif': imagegif($thumbnail, $basePath . $thumbnailFilename); break;
			}
			imagedestroy($thumbnail);
			imagedestroy($image);

			$category->setImage(array(
				'name' => $filename,
				'width' => $this->_sizeArray[0],
				'height' => $this->_sizeArray[1],
				'thumbnailName' => $thumbnailFilename,
				'thumbnailWidth' => $thumbnailWidth,
				'thumbnailHeight' => $thumbnailHeight,
				'uploadName' => $temporaryFile->getOriginalFileName(),
				'dateUploaded' => Core::getCurrentDate(),
			));

			// Clean up the temporary file
			import('lib.pkp.classes.file.TemporaryFileManager');
			$temporaryFileManager = new TemporaryFileManager();
			$temporaryFileManager->deleteById($temporaryFileId, $this->_userId);
		}

		// Update category object to store image information.
		$categoryDao->updateObject($category);
		return $category;
	}
}


