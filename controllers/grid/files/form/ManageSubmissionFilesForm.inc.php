<?php

/**
 * @file controllers/grid/files/form/ManageSubmissionFilesForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageSubmissionFilesForm
 * @ingroup controllers_grid_files_form
 *
 * @brief Form for add or removing files from a review
 */

use APP\facades\Repo;
use PKP\form\Form;
use PKP\submissionFile\SubmissionFile;

class ManageSubmissionFilesForm extends Form
{
    /** @var int */
    public $_submissionId;

    /**
     * Constructor.
     *
     * @param int $submissionId Submission ID
     * @param string $template Template filename
     */
    public function __construct($submissionId, $template)
    {
        parent::__construct($template);
        $this->_submissionId = (int)$submissionId;

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }


    //
    // Getters / Setters
    //
    /**
     * Get the submission id
     *
     * @return int
     */
    public function getSubmissionId()
    {
        return $this->_submissionId;
    }

    //
    // Overridden template methods
    //
    /**
     * @copydoc Form::initData
     */
    public function initData()
    {
        $this->setData('submissionId', $this->_submissionId);
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['selectedFiles']);
    }

    /**
     * Save selection of submission files
     *
     * @param array $stageSubmissionFiles The files that belongs to a file stage
     * that is currently being used by a grid inside this form.
     * @param int $fileStage SubmissionFile::SUBMISSION_FILE_...
     */
    public function execute($stageSubmissionFiles = null, $fileStage = null, ...$functionArgs)
    {
        $selectedFiles = (array)$this->getData('selectedFiles');
        $collector = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$this->getSubmissionId()]);
        $submissionFilesIterator = Repo::submissionFile()->getMany($collector);

        foreach ($submissionFilesIterator as $submissionFile) {
            // Get the viewable flag value.
            $isViewable = in_array(
                $submissionFile->getId(),
                $selectedFiles
            );

            // If this is a submission file that's already in this listing...
            if ($this->fileExistsInStage($submissionFile, $stageSubmissionFiles, $fileStage)) {
                // ...update the "viewable" flag accordingly.
                if ($isViewable != $submissionFile->getData('viewable')) {
                    $submissionFile = Repo::submissionFile()
                        ->edit(
                            $submissionFile,
                            ['viewable' => $isViewable]
                        );
                }
            } elseif ($isViewable) {
                // Import a file from a different workflow area
                $submissionFile = $this->importFile($submissionFile, $fileStage);
            }
        }

        parent::execute($stageSubmissionFiles = null, $fileStage = null, ...$functionArgs);
    }

    /**
     * Determine if a file with the same file stage is already present in the workflow stage.
     *
     * @param SubmissionFile $submissionFile The submission file
     * @param array $stageSubmissionFiles The list of submission files in the stage.
     * @param int $fileStage FILE_STAGE_...
     */
    protected function fileExistsInStage($submissionFile, $stageSubmissionFiles, $fileStage)
    {
        if (!isset($stageSubmissionFiles[$submissionFile->getId()])) {
            return false;
        }
        foreach ($stageSubmissionFiles[$submissionFile->getId()] as $stageFile) {
            if ($stageFile->getFileStage() == $submissionFile->getFileStage() && $stageFile->getFileStage() == $fileStage) {
                return true;
            }
        }
        return false;
    }

    /**
     * Make a copy of the file to the specified file stage.
     *
     * @param SubmissionFile $submissionFile
     * @param int $fileStage SubmissionFile::SUBMISSION_FILE_...
     *
     * @return SubmissionFile Resultant new submission file
     */
    protected function importFile($submissionFile, $fileStage)
    {
        $newSubmissionFile = clone $submissionFile;
        $newSubmissionFile->setData('fileStage', $fileStage);
        $newSubmissionFile->setData('sourceSubmissionFileId', $submissionFile->getId());
        $newSubmissionFileId = Repo::submissionFile()->add($newSubmissionFile);

        return Repo::submissionFile()->get($newSubmissionFileId);
    }
}
