<?php
/**
 * @file controllers/grid/files/review/ReviewRevisionsGridDataProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRevisionsGridDataProvider
 * @ingroup controllers_grid_files_review
 *
 * @brief Provide access to review revisions (new files added during a
 *  review round) for grids.
 */

namespace PKP\controllers\grid\files\review;

use APP\core\Application;
use APP\facades\Repo;
use PKP\controllers\api\file\linkAction\AddRevisionLinkAction;
use PKP\submissionFile\SubmissionFile;

class ReviewRevisionsGridDataProvider extends ReviewGridDataProvider
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $stageId = (int) Application::get()->getRequest()->getUserVar('stageId');
        $fileStage = $stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION : SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION;
        parent::__construct($fileStage);
    }


    //
    // Implement template methods from GridDataProvider
    //
    /**
     * @copydoc GridDataProvider::loadData()
     */
    public function loadData($filter = [])
    {
        // Grab the files that are new (incoming) revisions
        // of those currently assigned to the review round.
        $collector = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$this->getSubmission()->getId()])
            ->filterByReviewRoundIds([$this->getReviewRound()->getId()])
            ->filterByFileStages([(int) $this->getFileStage()]);

        $submissionFilesIterator = Repo::submissionFile()->getMany($collector);
        return $this->prepareSubmissionFileData(iterator_to_array($submissionFilesIterator), false, $filter);
    }


    //
    // Overridden public methods from FilesGridDataProvider
    //
    /**
     * @copydoc FilesGridDataProvider::getAddFileAction()
     */
    public function getAddFileAction($request)
    {
        $reviewRound = $this->getReviewRound();
        return new AddRevisionLinkAction(
            $request,
            $reviewRound,
            $this->getUploaderRoles()
        );
    }
}
