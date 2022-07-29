<?php
/**
 * @file controllers/grid/files/review/ReviewGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewGridDataProvider
 * @ingroup controllers_grid_files_review
 *
 * @brief Provide access to review file data for grids.
 */

namespace PKP\controllers\grid\files\review;

use APP\facades\Repo;
use PKP\controllers\api\file\linkAction\AddFileLinkAction;
use PKP\controllers\grid\files\fileList\linkAction\SelectReviewFilesLinkAction;
use PKP\controllers\grid\files\SubmissionFilesGridDataProvider;
use PKP\security\authorization\internal\ReviewRoundRequiredPolicy;

class ReviewGridDataProvider extends SubmissionFilesGridDataProvider
{
    /** @var bool */
    protected $_showAll;

    /**
     * Constructor
     *
     * @copydoc SubmissionFilesGridDataProvider::__construct()
     *
     * @param bool $showAll True iff all review round files should be included.
     */
    public function __construct($fileStageId, $viewableOnly = false, $showAll = false)
    {
        $this->_showAll = $showAll;
        parent::__construct($fileStageId, $viewableOnly);
    }


    //
    // Implement template methods from GridDataProvider
    //
    /**
     * @copydoc GridDataProvider::getAuthorizationPolicy()
     */
    public function getAuthorizationPolicy($request, $args, $roleAssignments)
    {
        // Get the parent authorization policy.
        $policy = parent::getAuthorizationPolicy($request, $args, $roleAssignments);

        // Add policy to ensure there is a review round id.
        $policy->addPolicy(new ReviewRoundRequiredPolicy($request, $args));

        return $policy;
    }

    /**
     * @copydoc GridDataProvider::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $reviewRound = $this->getReviewRound();
        return array_merge(
            parent::getRequestArgs(),
            [
                'reviewRoundId' => $reviewRound->getId()
            ]
        );
    }

    /**
     * @copydoc GridDataProvider::loadData()
     */
    public function loadData($filter = [])
    {
        // Get all review files assigned to this submission.
        $collector = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$this->getSubmission()->getId()])
            ->filterByReviewRoundIds([$this->getReviewRound()->getId()]);

        if (!$this->_showAll) {
            $collector = $collector->filterByFileStages([(int) $this->getFileStage()]);
        }

        $submissionFilesIterator = Repo::submissionFile()->getMany($collector);
        return $this->prepareSubmissionFileData(iterator_to_array($submissionFilesIterator), $this->_viewableOnly, $filter);
    }

    //
    // Overridden public methods from FilesGridDataProvider
    //
    /**
     * @copydoc FilesGridDataProvider::getSelectAction()
     */
    public function getSelectAction($request)
    {
        $reviewRound = $this->getReviewRound();
        $modalTitle = __('editor.submission.review.currentFiles', ['round' => $reviewRound->getRound()]);
        return new SelectReviewFilesLinkAction(
            $request,
            $reviewRound,
            __('editor.submission.uploadSelectFiles'),
            $modalTitle
        );
    }

    /**
     * @copydoc FilesGridDataProvider::getAddFileAction()
     */
    public function getAddFileAction($request)
    {
        $submission = $this->getSubmission();
        $reviewRound = $this->getReviewRound();

        return new AddFileLinkAction(
            $request,
            $submission->getId(),
            $this->getStageId(),
            $this->getUploaderRoles(),
            $this->getFileStage(),
            null,
            null,
            $reviewRound->getId()
        );
    }

    /**
     * Get the review round object.
     *
     * @return ReviewRound
     */
    public function getReviewRound()
    {
        $reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
        return $reviewRound;
    }
}
