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

use APP\facades\Repo;
use APP\handler\Handler;
use APP\submission\Collector;
use APP\submission\Submission;
use APP\template\TemplateManager;

use PKP\file\ContextFileManager;
use PKP\security\authorization\ContextRequiredPolicy;

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
     * @param array $args [
     *		@option string Category path
     *		@option int Page number if available
     * ]
     *
     * @param PKPRequest $request
     *
     * @return string
     */
    public function category($args, $request)
    {
        $page = isset($args[1]) ? (int) $args[1] : 1;
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        // Get the category
        $category = Repo::category()->getMany(
            Repo::category()->getCollector()
                ->filterByPaths([$args[0]])
                ->filterByContextIds([$context->getId()])
        )->first();
        if (!$category) {
            $this->getDispatcher()->handle404();
        }

        $this->setupTemplate($request);
        $orderOption = $category->getSortOption() ? $category->getSortOption() : Collector::ORDERBY_DATE_PUBLISHED . '-' . SORT_DIRECTION_DESC;
        [$orderBy, $orderDir] = explode('-', $orderOption);

        $count = $context->getData('itemsPerPage') ? $context->getData('itemsPerPage') : Config::getVar('interface', 'items_per_page');
        $offset = $page > 1 ? ($page - 1) * $count : 0;

        $collector = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByCategoryIds([$category->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->orderBy($orderBy, $orderDir === SORT_DIRECTION_ASC ? Collector::ORDER_DIR_ASC : Collector::ORDER_DIR_DESC);

        // Featured items are only in OMP at this time
        if (method_exists($collector, 'orderByFeatured')) {
            $collector->orderByFeatured(true);
        }

        $total = Repo::submission()->getCount($collector);
        $submissions = Repo::submission()->getMany($collector->limit($count)->offset($offset));

        // Provide the parent category and a list of subcategories
        $parentCategory = $category->getParentId() ? Repo::category()->get($category->getParentId()) : null;
        $subcategories = Repo::category()->getMany(
            Repo::category()->getCollector()
                ->filterByParentIds([$category->getId()])
        );

        $this->_setupPaginationTemplate($request, count($submissions), $page, $count, $offset, $total);

        $templateMgr->assign([
            'category' => $category,
            'parentCategory' => $parentCategory,
            'subcategories' => iterator_to_array($subcategories),
            'publishedSubmissions' => $submissions->toArray(),
        ]);

        return $templateMgr->display('frontend/pages/catalogCategory.tpl');
    }

    /**
     * Serve the full sized image for a category.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function fullSize($args, $request)
    {
        switch ($request->getUserVar('type')) {
            case 'category':
                $context = $request->getContext();
                $category = Repo::category()->get((int) $request->getUserVar('id'));
                if (!$category || $category->getContextId() != $context->getId()) {
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
     * @param array $args
     * @param PKPRequest $request
     */
    public function thumbnail($args, $request)
    {
        switch ($request->getUserVar('type')) {
            case 'category':
                $context = $request->getContext();
                $category = Repo::category()->get((int) $request->getUserVar('id'));
                if (!$category || $category->getContextId() != $context->getId()) {
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
     * Assign the pagination template variables
     *
     * @param PKPRequest $request
     * @param int $submissionsCount Number of monographs being shown
     * @param int $page Page number being shown
     * @param int $count Max number of monographs being shown
     * @param int $offset Starting position of monographs
     * @param int $total Total number of monographs available
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
