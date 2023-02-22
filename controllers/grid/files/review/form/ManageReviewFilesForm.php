<?php

/**
 * @file controllers/grid/files/review/form/ManageReviewFilesForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageReviewFilesForm
 * @ingroup controllers_grid_files_review_form
 *
 * @brief Form for add or removing files from a review
 */

namespace PKP\controllers\grid\files\review\form;

use APP\facades\Repo;
use PKP\controllers\grid\files\form\ManageSubmissionFilesForm;
use PKP\db\DAORegistry;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submissionFile\SubmissionFile;

class ManageReviewFilesForm extends ManageSubmissionFilesForm
{
    /** @var int */
    public $_stageId;

    /** @var int */
    public $_reviewRoundId;


    /**
     * Constructor.
     */
    public function __construct($submissionId, $stageId, $reviewRoundId)
    {
        parent::__construct($submissionId, 'controllers/grid/files/review/manageReviewFiles.tpl');
        $this->_stageId = (int)$stageId;
        $this->_reviewRoundId = (int)$reviewRoundId;
    }


    //
    // Getters / Setters
    //
    /**
     * Get the review stage id
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->_stageId;
    }

    /**
     * Get the round
     *
     * @return int
     */
    public function getReviewRoundId()
    {
        return $this->_reviewRoundId;
    }

    /**
     * @return ReviewRound
     */
    public function getReviewRound()
    {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        return $reviewRoundDao->getById($this->getReviewRoundId());
    }


    //
    // Overridden template methods
    //
    /**
     * @copydoc ManageSubmissionFilesForm::initData
     */
    public function initData()
    {
        $this->setData('stageId', $this->getStageId());
        $this->setData('reviewRoundId', $this->getReviewRoundId());

        $reviewRound = $this->getReviewRound();
        $this->setData('round', $reviewRound->getRound());

        parent::initData();
    }

    /**
     * Save review round files
     *
     * @stageSubmissionFiles array The files that belongs to a file stage
     * that is currently being used by a grid inside this form.
     *
     * @param int $fileStage SubmissionFile::SUBMISSION_FILE_...
     * @param null|mixed $stageSubmissionFiles
     */
    public function execute($stageSubmissionFiles = null, $fileStage = null, ...$functionArgs)
    {
        parent::execute(
            $stageSubmissionFiles,
            $this->getReviewRound()->getStageId() == WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE : SubmissionFile::SUBMISSION_FILE_REVIEW_FILE
        );
    }

    /**
     * @copydoc ManageSubmissionFilesForm::importFile()
     */
    protected function importFile($submissionFile, $fileStage)
    {
        $newSubmissionFile = parent::importFile($submissionFile, $fileStage);

        Repo::submissionFile()
            ->dao
            ->assignRevisionToReviewRound(
                $newSubmissionFile,
                $this->getReviewRound()
            );

        return $newSubmissionFile;
    }
}
