<?php
/**
 * @file controllers/grid/files/SubmissionFilesGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFilesGridDataProvider
 * @ingroup controllers_grid_files
 *
 * @brief Provide access to submission file data for grids.
 */

import('lib.pkp.controllers.grid.files.FilesGridDataProvider');

use APP\facades\Repo;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\facades\Locale;

class SubmissionFilesGridDataProvider extends FilesGridDataProvider
{
    /** @var int */
    public $_stageId;

    /** @var int */
    public $_fileStage;


    /**
     * Constructor
     *
     * @param int $fileStage One of the SubmissionFile::SUBMISSION_FILE_* constants.
     * @param bool $viewableOnly True iff only viewable files should be included.
     */
    public function __construct($fileStage, $viewableOnly = false)
    {
        assert(is_numeric($fileStage) && $fileStage > 0);
        $this->_fileStage = (int)$fileStage;
        parent::__construct();

        $this->setViewableOnly($viewableOnly);
    }


    //
    // Getters and setters.
    //
    /**
     * Set the workflow stage.
     *
     * @param int $stageId WORKFLOW_STAGE_ID_...
     */
    public function setStageId($stageId)
    {
        $this->_stageId = $stageId;
    }

    /**
     * Get the workflow stage.
     *
     * @return int WORKFLOW_STAGE_ID_...
     */
    public function getStageId()
    {
        return $this->_stageId;
    }


    //
    // Implement template methods from GridDataProvider
    //
    /**
     * @copydoc GridDataProvider::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $submission = $this->getSubmission();
        return [
            'submissionId' => $submission->getId(),
            'stageId' => $this->getStageId(),
            'fileStage' => $this->getFileStage(),
        ];
    }

    /**
     * Get the file stage.
     *
     * @return int SubmissionFile::SUBMISSION_FILE_...
     */
    public function getFileStage()
    {
        return $this->_fileStage;
    }

    /**
     * @copydoc GridDataProvider::loadData()
     */
    public function loadData($filter = [])
    {
        $collector = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$this->getSubmission()->getId()])
            ->filterByFileStages([$this->getFileStage()]);
        $submissionFilesIterator = Repo::submissionFile()->getMany($collector);
        return $this->prepareSubmissionFileData(iterator_to_array($submissionFilesIterator), $this->_viewableOnly, $filter);
    }

    //
    // Implement template methods from GridDataProvider
    //
    /**
     * @copydoc GridDataProvider::getAuthorizationPolicy()
     */
    public function getAuthorizationPolicy($request, $args, $roleAssignments)
    {
        $this->setUploaderRoles($roleAssignments);

        return new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $this->getStageId());
    }

    //
    // Overridden public methods from FilesGridDataProvider
    //
    /**
     * @copydoc FilesGridDataProvider::getAddFileAction()
     */
    public function getAddFileAction($request)
    {
        import('lib.pkp.controllers.api.file.linkAction.AddFileLinkAction');
        $submission = $this->getSubmission();
        return new AddFileLinkAction(
            $request,
            $submission->getId(),
            $this->getStageId(),
            $this->getUploaderRoles(),
            $this->getFileStage()
        );
    }


    //
    // Protected functions
    //
    /**
     * Apply the filter to the list of revisions, returning only matching elements.
     *
     * @param array $revisions List of potential submission files to include.
     * @param array $filter Associative array of filter data
     *
     * @return array
     */
    protected function applyFilter($revisions, $filter)
    {
        if (!empty($filter['search'])) {
            switch ($filter['column']) {
            case 'name':
                foreach ($revisions as $key => $submissionFile) {
                    if (!stristr($submissionFile->getData('name', Locale::getLocale()), $filter['search'])) {
                        unset($revisions[$key]);
                    }
                }
                break;
        }
        }
        return $revisions;
    }

    /**
     * Rearrange file revisions by file id and return the file
     * data wrapped into an array so that grid implementations
     * can add further data.
     *
     * @param array $revisions List of SubmissionFiles
     * @param bool $viewableOnly optional True iff only viewable files should be listed
     * @param array $filter optional Associative array of filter conditions
     *
     * @return array
     */
    public function prepareSubmissionFileData($revisions, $viewableOnly = false, $filter = [])
    {
        $revisions = $this->applyFilter($revisions, $filter);

        // Rearrange the files as required by submission file grids.
        $submissionFileData = [];
        foreach ($revisions as $revision) {
            if ($viewableOnly && !$revision->getViewable()) {
                continue;
            }

            $submissionFileData[$revision->getId()] = [
                'submissionFile' => $revision
            ];
        }
        return $submissionFileData;
    }
}
