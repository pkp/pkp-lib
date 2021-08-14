<?php

/**
 * @file controllers/grid/files/SelectableSubmissionFileListCategoryGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectableSubmissionFileListCategoryGridHandler
 * @ingroup controllers_grid_files
 *
 * @brief Handle selectable submission file list category grid requests.
 */

use PKP\controllers\grid\CategoryGridHandler;
use PKP\controllers\grid\feature\selectableItems\SelectableItemsFeature;
use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\controllers\grid\GridHandler;

// Import submission files grid specific classes.
import('lib.pkp.controllers.grid.files.SubmissionFilesGridRow');
import('lib.pkp.controllers.grid.files.FileNameGridColumn');
import('lib.pkp.controllers.grid.files.SelectableSubmissionFileListCategoryGridRow');

class SelectableSubmissionFileListCategoryGridHandler extends CategoryGridHandler
{
    /** @var FilesGridCapabilities */
    public $_capabilities;

    /** @var int */
    public $_stageId;

    /**
     * Constructor
     *
     * @param GridDataProvider $dataProvider
     * @param int $stageId One of the WORKFLOW_STAGE_ID_* constants.
     * @param int $capabilities A bit map with zero or more
     *  FILE_GRID_* capabilities set.
     */
    public function __construct($dataProvider, $stageId, $capabilities = 0)
    {
        // the StageId can be set later if necessary.
        if ($stageId) {
            $this->_stageId = (int)$stageId;
        }

        $this->_capabilities = new FilesGridCapabilities($capabilities);

        parent::__construct($dataProvider);
    }


    //
    // Getters and Setters
    //
    /**
     * Get grid capabilities object.
     *
     * @return FilesGridCapabilities
     */
    public function getCapabilities()
    {
        return $this->_capabilities;
    }

    /**
     * Get the workflow stage id.
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->_stageId;
    }

    /**
     * Get the authorized submission.
     *
     * @return Submission
     */
    public function getSubmission()
    {
        // We assume proper authentication by the data provider.
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        assert($submission instanceof \APP\submission\Submission);
        return $submission;
    }


    //
    // Overridden methods from GridHandler
    //
    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        // Let parent class get data from data provider.
        $workflowStages = parent::loadData($request, $filter);

        // Filter the data.
        if ($filter['allStages']) {
            return array_combine($workflowStages, $workflowStages);
        } else {
            return [$this->getStageId() => $this->getStageId()];
        }
    }

    /**
     * @copydoc GridHandler::getFilterForm()
     */
    protected function getFilterForm()
    {
        return 'controllers/grid/files/selectableSubmissionFileListCategoryGridFilter.tpl';
    }

    /**
     * @copydoc GridHandler::isFilterFormCollapsible()
     */
    protected function isFilterFormCollapsible()
    {
        return false;
    }

    /**
     * @copydoc GridHandler::getFilterSelectionData()
     */
    public function getFilterSelectionData($request)
    {
        return ['allStages' => $request->getUserVar('allStages') ? true : false];
    }


    //
    // Overridden methods from CategoryGridHandler
    //
    /**
     * @copydoc CategoryGridHandler::getCategoryRowInstance()
     */
    protected function getCategoryRowInstance()
    {
        return new SelectableSubmissionFileListCategoryGridRow();
    }


    //
    // Implement template methods from PKPHandler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // Set the stage id from the request parameter if not set previously.
        if (!$this->getStageId()) {
            $stageId = (int) $request->getUserVar('stageId');
            // This will be validated with the authorization policy added by
            // the grid data provider.
            $this->_stageId = $stageId;
        }

        $dataProvider = $this->getDataProvider();
        $dataProvider->setStageId($this->getStageId());

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc CategoryGridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Add grid actions
        $capabilities = $this->getCapabilities();
        $dataProvider = $this->getDataProvider();

        if ($capabilities->canManage()) {
            $this->addAction($dataProvider->getSelectAction($request));
        }

        if ($capabilities->canAdd()) {
            assert(isset($dataProvider));
            $this->addAction($dataProvider->getAddFileAction($request));
        }

        // Test whether an archive tool is available for the export to work, if so, add 'download all' grid action
        if ($capabilities->canDownloadAll() && $this->hasGridDataElements($request)) {
            $submission = $this->getSubmission();
            $stageId = $this->getStageId();
            $linkParams = [
                'nameLocaleKey' => $this->getTitle(),
                'submissionId' => $submission->getId(),
                'stageId' => $stageId,
            ];
            $files = $this->getFilesToDownload($request);

            $this->addAction($capabilities->getDownloadAllAction($request, $files, $linkParams), GridHandler::GRID_ACTION_POSITION_BELOW);
        }

        // The file name column is common to all file grid types.
        $this->addColumn(new FileNameGridColumn($capabilities->canViewNotes(), $this->getStageId()));

        // The file list grid layout has an additional file genre column.
        import('lib.pkp.controllers.grid.files.fileList.FileGenreGridColumn');
        $this->addColumn(new FileGenreGridColumn());

        // Set the no items row text
        $this->setEmptyRowText('grid.noFiles');
    }

    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new SelectableItemsFeature()];
    }


    //
    // Overridden methods from GridHandler
    //
    /**
     * @copydoc GridHandler::getRowInstance()
     */
    protected function getRowInstance()
    {
        return new SubmissionFilesGridRow($this->getCapabilities(), $this->getStageId());
    }


    //
    // Protected methods
    //
    /**
     * Get all files of this grid to download.
     *
     * @param Request $request
     *
     * @return array
     */
    public function getFilesToDownload($request)
    {
        $dataProvider = $this->getDataProvider();
        $workflowStages = $this->getGridDataElements($request);

        // Get the submission files to be downloaded.
        $submissionFiles = [];
        foreach ($workflowStages as $stageId) {
            $submissionFiles = array_merge(
                $submissionFiles,
                $this->getGridCategoryDataElements($request, $stageId)
            );
        }
        return $submissionFiles;
    }

    /**
     * @copydoc GridHandler::isDataElementInCategorySelected()
     */
    public function isDataElementInCategorySelected($categoryDataId, &$gridDataElement)
    {
        $currentStageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        $submissionFile = $gridDataElement['submissionFile'];

        // Check for special cases when the file needs to be unselected.
        $dataProvider = $this->getDataProvider();
        if ($dataProvider->getFileStage() != $submissionFile->getFileStage()) {
            return false;
        } elseif ($currentStageId == WORKFLOW_STAGE_ID_INTERNAL_REVIEW || $currentStageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            if ($currentStageId != $categoryDataId) {
                return false;
            }
        }

        // Passed the checks above. If viewable then select it.
        return $submissionFile->getViewable();
    }

    /**
     * Get the selection name.
     *
     * @return string
     */
    public function getSelectName()
    {
        return 'selectedFiles';
    }
}
