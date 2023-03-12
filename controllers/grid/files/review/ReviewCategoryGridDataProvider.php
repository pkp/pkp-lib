<?php
/**
 * @file controllers/grid/files/review/ReviewCategoryGridDataProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewGridCategoryDataProvider
 * @ingroup controllers_grid_files_review
 *
 * @brief Provide access to review file data for category grids.
 */

namespace PKP\controllers\grid\files\review;

use PKP\controllers\grid\files\SubmissionFilesCategoryGridDataProvider;

class ReviewCategoryGridDataProvider extends SubmissionFilesCategoryGridDataProvider
{
    /**
     * Constructor
     *
     * @param int $fileStage
     * @param int $viewableOnly Will be passed to the review grid data provider.
     * See parameter description there.
     */
    public function __construct($fileStage, $viewableOnly = false)
    {
        parent::__construct($fileStage, ['viewableOnly' => $viewableOnly]);
    }


    //
    // Getters and setters.
    //
    /**
     * @return ReviewRound
     */
    public function getReviewRound()
    {
        /** @var ReviewGridDataProvider */
        $gridDataProvider = $this->getDataProvider();
        return $gridDataProvider->getReviewRound();
    }


    //
    // Overriden public methods from SubmissionFilesCategoryGridDataProvider
    //
    /**
     * @copydoc SubmissionFilesCategoryGridDataProvider::loadCategoryData()
     *
     * @param null|mixed $filter
     * @param null|mixed $reviewRound
     */
    public function loadCategoryData($request, $categoryDataElement, $filter = null, $reviewRound = null)
    {
        $reviewRound = $this->getReviewRound();
        return parent::loadCategoryData($request, $categoryDataElement, $filter, $reviewRound);
    }

    /**
     * @copydoc SubmissionFilesCategoryGridDataProvider::initGridDataProvider()
     *
     * @param null|mixed $initParams
     */
    public function initGridDataProvider($fileStage, $initParams = null)
    {
        // This category grid data provider will use almost all the
        // same implementation of the ReviewGridDataProvider.
        $reviewFilesGridDataProvider = new ReviewGridDataProvider($fileStage);
        $reviewFilesGridDataProvider->setViewableOnly($initParams['viewableOnly']);

        return $reviewFilesGridDataProvider;
    }


    //
    // Public methods
    //
    /**
     * @copydoc ReviewGridDataProvider::getSelectAction()
     */
    public function getSelectAction($request)
    {
        /** @var ReviewGridDataProvider */
        $gridDataProvider = $this->getDataProvider();
        return $gridDataProvider->getSelectAction($request);
    }
}
