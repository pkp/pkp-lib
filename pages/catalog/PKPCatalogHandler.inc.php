<?php

/**
 * @file pages/catalog/PKPCatalogHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCatalogHandler
 * @ingroup pages_catalog
 *
 * @brief Handle requests for the public-facing catalog.
 */

use APP\core\Services;
use APP\handler\Handler;
use APP\template\TemplateManager;

use PKP\file\ContextFileManager;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\submission\PKPSubmission;

class PKPCatalogHandler extends Handler
{
    //
    // Overridden methods from Handler
    //
    /**
     * @see PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextRequiredPolicy($request));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * View the content of a category.
     *
     * @param $args array [
     *		@option string Category path
     *		@option int Page number if available
     * ]
     *
     * @param $request PKPRequest
     *
     * @return string
     */
    public function category($args, $request)
    {
        $page = isset($args[1]) ? (int) $args[1] : 1;
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        // Get the category
        $categoryDao = DAORegistry::getDAO('CategoryDAO'); /** @var CategoryDAO $categoryDao */
        $category = $categoryDao->getByPath($args[0], $context->getId());
        if (!$category) {
            $this->getDispatcher()->handle404();
        }

        $this->setupTemplate($request);
        $orderOption = $category->getSortOption() ? $category->getSortOption() : ORDERBY_DATE_PUBLISHED . '-' . SORT_DIRECTION_DESC;
        [$orderBy, $orderDir] = explode('-', $orderOption);

        $count = $context->getData('itemsPerPage') ? $context->getData('itemsPerPage') : Config::getVar('interface', 'items_per_page');
        $offset = $page > 1 ? ($page - 1) * $count : 0;

        $params = [
            'contextId' => $context->getId(),
            'categoryIds' => $category->getId(),
            'orderByFeatured' => true,
            'orderBy' => $orderBy,
            'orderDirection' => $orderDir == SORT_DIRECTION_ASC ? 'ASC' : 'DESC',
            'count' => $count,
            'offset' => $offset,
            'status' => PKPSubmission::STATUS_PUBLISHED,
        ];
        $submissionsIterator = Services::get('submission')->getMany($params);
        $total = Services::get('submission')->getMax($params);

        // Provide the parent category and a list of subcategories
        $parentCategory = $categoryDao->getById($category->getParentId());
        $subcategories = $categoryDao->getByParentId($category->getId());

        $this->_setupPaginationTemplate($request, count($submissionsIterator), $page, $count, $offset, $total);

        $templateMgr->assign([
            'category' => $category,
            'parentCategory' => $parentCategory,
            'subcategories' => $subcategories->toArray(),
            'publishedSubmissions' => iterator_to_array($submissionsIterator),
        ]);

        return $templateMgr->display('frontend/pages/catalogCategory.tpl');
    }

    /**
     * Serve the full sized image for a category.
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function fullSize($args, $request)
    {
        switch ($request->getUserVar('type')) {
            case 'category':
                $context = $request->getContext();
                $categoryDao = DAORegistry::getDAO('CategoryDAO'); /** @var CategoryDAO $categoryDao */
                $category = $categoryDao->getById($request->getUserVar('id'), $context->getId());
                if (!$category) {
                    $this->getDispatcher()->handle404();
                }
                $imageInfo = $category->getImage();
                $contextFileManager = new ContextFileManager($context->getId());
                $contextFileManager->downloadByPath($contextFileManager->getBasePath() . '/categories/' . $imageInfo['name'], null, true);
                break;
            default:
                fatalError('invalid type specified');
        }
    }

    /**
     * Serve the thumbnail for a category.
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function thumbnail($args, $request)
    {
        switch ($request->getUserVar('type')) {
            case 'category':
                $context = $request->getContext();
                $categoryDao = DAORegistry::getDAO('CategoryDAO'); /** @var CategoryDAO $categoryDao */
                $category = $categoryDao->getById($request->getUserVar('id'), $context->getId());
                if (!$category) {
                    $this->getDispatcher()->handle404();
                }
                $imageInfo = $category->getImage();
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
    public function setupTemplate($request)
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION);
        parent::setupTemplate($request);
    }

    /**
     * Assign the pagination template variables
     *
     * @param $request PKPRequest
     * @param $submissionsCount int Number of monographs being shown
     * @param $page int Page number being shown
     * @param $count int Max number of monographs being shown
     * @param $offset int Starting position of monographs
     * @param $total int Total number of monographs available
     */
    protected function _setupPaginationTemplate($request, $submissionsCount, $page, $count, $offset, $total)
    {
        $showingStart = $offset + 1;
        $showingEnd = min($offset + $count, $offset + $submissionsCount);
        $nextPage = $total > $showingEnd ? $page + 1 : null;
        $prevPage = $showingStart > 1 ? $page - 1 : null;

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'showingStart' => $showingStart,
            'showingEnd' => $showingEnd,
            'total' => $total,
            'nextPage' => $nextPage,
            'prevPage' => $prevPage,
        ]);
    }
}
