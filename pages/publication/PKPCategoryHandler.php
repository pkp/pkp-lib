<?php

/**
 * @file pages/publication/PKPCategoryHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCategoryHandler
 * @ingroup pages_publication
 *
 * @brief Handle requests for the public-facing category listing.
 */

namespace PKP\pages\publication;

use APP\facades\Repo;
use APP\handler\Handler;
use APP\submission\Collector;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\file\ContextFileManager;
use PKP\security\authorization\ContextRequiredPolicy;

class PKPCategoryHandler extends Handler
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
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        $categoryPath = empty($args) ? '' : array_shift($args);
        $subPath = empty($args) ? '' : array_shift($args);
        $page = 0;

        if ($subPath !== 'page') {
            $subCategoryPath = $subPath;
        }
        if ($subPath === 'page') {
            $page = (int) array_shift($args);
        }

        // Get the category
        $parentCategory = Repo::category()->getCollector()
                ->filterByPaths([$categoryPath])
                ->filterByContextIds([$context->getId()])
                ->getMany()
                ->first();

        // If subcategory exists, fetch that as well
        if ($subCategoryPath){
            $category = Repo::category()->getCollector()
                    ->filterByPaths([$subCategoryPath])
                    ->filterByContextIds([$context->getId()])
                    ->getMany()
                    ->first();
            if (!$category || !$parentCategory || $category->getParentId() != $parentCategory->getId()) {
                $this->getDispatcher()->handle404();
            }
        } else {
            $category = $parentCategory;
            if (!$category) {
                $this->getDispatcher()->handle404();
            }
        }

        $this->setupTemplate($request);
        $orderOption = $category->getSortOption() ? $category->getSortOption() : Collector::ORDERBY_DATE_PUBLISHED . '-' . SORT_DIRECTION_DESC;
        [$orderBy, $orderDir] = explode('-', $orderOption);

        $count = $context->getData('itemsPerPage') ? $context->getData('itemsPerPage') : Config::getVar('interface', 'items_per_page');
        $offset = $page > 1 ? ($page - 1) * $count : 0;

        // Provide the parent category and a list of subcategories
        $parentCategory = $category->getParentId() ? Repo::category()->get($category->getParentId()) : null;
        $subcategories = Repo::category()->getCollector()
            ->filterByParentIds([$category->getId()])
            ->getMany();

        // Get category id's for parent and subcategories
        $categoryIds[] = $category->getId();
        foreach ($subcategories as $subcategory) {
            $categoryIds[] = $subcategory->getId();
        }

        $collector = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByCategoryIds($categoryIds)
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->orderBy($orderBy, $orderDir === SORT_DIRECTION_ASC ? Collector::ORDER_DIR_ASC : Collector::ORDER_DIR_DESC);

        // Featured items are only in OMP at this time
        if (method_exists($collector, 'orderByFeatured')) {
            $collector->orderByFeatured(true);
        }

        $total = $collector->getCount();
        $submissions = $collector->limit($count)->offset($offset)->getMany();

        $this->_setupPaginationTemplate($request, count($submissions), $page, $count, $offset, $total);

        $authorUserGroups = Repo::userGroup()->getCollector()->filterByRoleIds([\PKP\security\Role::ROLE_ID_AUTHOR])->getMany();

        $templateMgr->assign([
            'authorUserGroups' => $authorUserGroups,
            'category' => $category,
            'parentCategory' => $parentCategory,
            'subcategories' => iterator_to_array($subcategories),
            'publishedSubmissions' => $submissions->toArray(),
        ]);

        return $templateMgr->display('frontend/pages/category.tpl');
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
     * @param int $submissionsCount Number of submission being shown
     * @param int $page Page number being shown
     * @param int $count Max number of submission being shown
     * @param int $offset Starting position of submission
     * @param int $total Total number of submission available
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
