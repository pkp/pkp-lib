<?php

/**
 * @file pages/catalog/PKPCatalogHandler.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCatalogHandler
 *
 * @ingroup pages_catalog
 *
 * @brief Handle requests for the public-facing catalog.
 */

namespace PKP\pages\catalog;

use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\handler\Handler;
use APP\submission\Collector;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;
use PKP\search\SubmissionSearchResult;
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
     *
     *        @option string Category path
     *        @option int Page number if available
     * ]
     *
     * @param PKPRequest $request
     */
    public function category($args, $request)
    {
        $page = isset($args[1]) ? (int) $args[1] : 1;
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        // Get the category
        $category = Repo::category()->getCollector()
            ->filterByPaths([$args[0]])
            ->filterByContextIds([$context->getId()])
            ->getMany()
            ->first();

        if (!$category) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $this->setupTemplate($request);
        $orderOption = $category->getSortOption() ? $category->getSortOption() : Collector::ORDERBY_DATE_PUBLISHED . '-' . Collector::ORDER_DIR_DESC;
        [$orderBy, $orderDir] = explode('-', $orderOption);

        $rangeInfo = $this->getRangeInfo($request, 'category');
        $builder = (new SubmissionSearchResult())->builderFromRequest($request, $rangeInfo);
        $builder->whereIn('categoryIds', [$category->getId()]);
        $results = $builder->paginate($rangeInfo->getCount(), 'submissions', $rangeInfo->getPage());

        /*        $collector = Repo::submission() // FIXME FIXME FIXME
                    ->orderBy($orderBy, $orderDir);

                // Featured items are only in OMP at this time
                if (method_exists($collector, 'orderByFeatured')) {
                    $collector->orderByFeatured(true);
                }
        */

        // Provide the parent category and a list of subcategories
        $parentCategory = $category->getParentId() ? Repo::category()->get($category->getParentId()) : null;
        $subcategories = Repo::category()->getCollector()
            ->filterByParentIds([$category->getId()])
            ->getMany();

        $templateMgr->assign([
            'category' => $category,
            'parentCategory' => $parentCategory,
            'subcategories' => iterator_to_array($subcategories),
            'results' => $results,
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
                    throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
                }
                $imageInfo = $category->getImage();
                $publicFileManager = new PublicFileManager();
                $publicFileManager->downloadByPath($publicFileManager->getContextFilesPath($category->getContextId()) . '/' . $imageInfo['uploadName'], null, true);
                break;
            default:
                throw new \Exception('invalid type specified');
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
                    throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
                }
                $imageInfo = $category->getImage();
                $publicFileManager = new PublicFileManager();
                $publicFileManager->downloadByPath($publicFileManager->getContextFilesPath($category->getContextId()) . '/' . $imageInfo['thumbnailName'], null, true);
                break;
            default:
                throw new \Exception('invalid type specified');
        }
    }
}
