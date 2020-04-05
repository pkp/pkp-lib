<?php

/**
 * @file pages/catalog/PKPCatalogHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCatalogHandler
 * @ingroup pages_catalog
 *
 * @brief Handle requests for the public-facing catalog.
 */

import('classes.handler.Handler');

// import UI base classes
import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.core.JSONMessage');

class PKPCatalogHandler extends Handler {
	//
	// Overridden methods from Handler
	//
	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextRequiredPolicy');
		$this->addPolicy(new ContextRequiredPolicy($request));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * View the content of a category.
	 * @param $args array [
	 *		@option string Category path
	 *		@option int Page number if available
	 * ]
	 * @param $request PKPRequest
	 * @return string
	 */
	function category($args, $request) {
		$page = isset($args[1]) ? (int) $args[1] : 1;
		$templateMgr = TemplateManager::getManager($request);
		$context = $request->getContext();

		// Get the category
		$categoryDao = DAORegistry::getDAO('CategoryDAO'); /* @var $categoryDao CategoryDAO */
		$category = $categoryDao->getByPath($args[0], $context->getId());
		if (!$category) $this->getDispatcher()->handle404();

		$this->setupTemplate($request);
		import('lib.pkp.classes.submission.PKPSubmission'); // STATUS_ constants

		$orderOption = $category->getSortOption() ? $category->getSortOption() : ORDERBY_DATE_PUBLISHED . '-' . SORT_DIRECTION_DESC;
		list($orderBy, $orderDir) = explode('-', $orderOption);

		$count = $context->getData('itemsPerPage') ? $context->getData('itemsPerPage') : Config::getVar('interface', 'items_per_page');
		$offset = $page > 1 ? ($page - 1) * $count : 0;

		import('classes.core.Services');
		$params = array(
			'contextId' => $context->getId(),
			'categoryIds' => $category->getId(),
			'orderByFeatured' => true,
			'orderBy' => $orderBy,
			'orderDirection' => $orderDir == SORT_DIRECTION_ASC ? 'ASC' : 'DESC',
			'count' => $count,
			'offset' => $offset,
			'status' => STATUS_PUBLISHED,
		);
		$submissionsIterator = Services::get('submission')->getMany($params);
		$total = Services::get('submission')->getMax($params);

		// Provide the parent category and a list of subcategories
		$parentCategory = $categoryDao->getById($category->getParentId());
		$subcategories = $categoryDao->getByParentId($category->getId());

		$this->_setupPaginationTemplate($request, count($submissionsIterator), $page, $count, $offset, $total);

		$templateMgr->assign(array(
			'category' => $category,
			'parentCategory' => $parentCategory,
			'subcategories' => $subcategories,
			'publishedSubmissions' => iterator_to_array($submissionsIterator),
		));

		return $templateMgr->display('frontend/pages/catalogCategory.tpl');
	}

	/**
	 * Serve the full sized image for a category.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function fullSize($args, $request) {
		switch ($request->getUserVar('type')) {
			case 'category':
				$context = $request->getContext();
				$categoryDao = DAORegistry::getDAO('CategoryDAO'); /* @var $categoryDao CategoryDAO */
				$category = $categoryDao->getById($request->getUserVar('id'), $context->getId());
				if (!$category) $this->getDispatcher()->handle404();
				$imageInfo = $category->getImage();
				import('lib.pkp.classes.file.ContextFileManager');
				$contextFileManager = new ContextFileManager($context->getId());
				$contextFileManager->downloadByPath($contextFileManager->getBasePath() . '/categories/' . $imageInfo['name'], null, true);
				break;
			default:
				fatalError('invalid type specified');
		}
	}

	/**
	 * Serve the thumbnail for a category.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function thumbnail($args, $request) {
		switch ($request->getUserVar('type')) {
			case 'category':
				$context = $request->getContext();
				$categoryDao = DAORegistry::getDAO('CategoryDAO'); /* @var $categoryDao CategoryDAO */
				$category = $categoryDao->getById($request->getUserVar('id'), $context->getId());
				if (!$category) $this->getDispatcher()->handle404();
				$imageInfo = $category->getImage();
				import('lib.pkp.classes.file.ContextFileManager');
				$contextFileManager = new ContextFileManager($context->getId());
				$contextFileManager->downloadByPath($contextFileManager->getBasePath() . '/categories/' . $imageInfo['thumbnailName'], null, true);
				break;
			default:
				fatalError('invalid type specified');
		}
	}

	/**
	 * Set up the basic template.
	 */
	function setupTemplate($request) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION);
		parent::setupTemplate($request);
	}

	/**
	 * Assign the pagination template variables
	 * @param $request PKPRequest
	 * @param $submissionsCount int Number of monographs being shown
	 * @param $page int Page number being shown
	 * @param $count int Max number of monographs being shown
	 * @param $offset int Starting position of monographs
	 * @param $total int Total number of monographs available
	 */
	protected function _setupPaginationTemplate($request, $submissionsCount, $page, $count, $offset, $total) {
		$showingStart = $offset + 1;
		$showingEnd = min($offset + $count, $offset + $submissionsCount);
		$nextPage = $total > $showingEnd ? $page + 1 : null;
		$prevPage = $showingStart > 1 ? $page - 1 : null;

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'showingStart' => $showingStart,
			'showingEnd' => $showingEnd,
			'total' => $total,
			'nextPage' => $nextPage,
			'prevPage' => $prevPage,
		));
	}
}
