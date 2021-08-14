<?php

/**
 * @file controllers/grid/files/SubmissionFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesGridHandler
 * @ingroup controllers_grid_files
 *
 * @brief Handle submission file grid requests.
 */

// Import submission files grid specific classes.
import('lib.pkp.controllers.grid.files.SubmissionFilesGridRow');
import('lib.pkp.controllers.grid.files.FileNameGridColumn');
import('lib.pkp.controllers.grid.files.FileDateGridColumn');

use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\controllers\grid\GridHandler;

class SubmissionFilesGridHandler extends GridHandler
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
        parent::__construct($dataProvider);

        if ($stageId) {
            $this->_stageId = (int)$stageId;
        }
        $this->_capabilities = new FilesGridCapabilities($capabilities);
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
     * Set grid capabilities object.
     *
     * @param FilesGridCapabilities $capabilities
     */
    public function setCapabilities($capabilities)
    {
        $this->_capabilities = $capabilities;
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
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Add grid actions
        $capabilities = $this->getCapabilities();
        $dataProvider = $this->getDataProvider();

        $submission = $this->getSubmission();

        if ($capabilities->canAdd()) {
            assert(isset($dataProvider));
            $this->addAction($dataProvider->getAddFileAction($request));
        }

        // Test whether an archive tool is available for the export to work, if so, add 'download all' grid action
        if ($capabilities->canDownloadAll() && $this->hasGridDataElements($request)) {
            $stageId = $this->getStageId();
            $linkParams = [
                'nameLocaleKey' => $this->getTitle(),
                'fileStage' => $this->getDataProvider()->getFileStage(),
                'submissionId' => $submission->getId(),
                'stageId' => $stageId,
            ];
            $files = $this->getFilesToDownload($request);

            $this->addAction($capabilities->getDownloadAllAction($request, $files, $linkParams), GridHandler::GRID_ACTION_POSITION_BELOW);
        }

        // The file name column is common to all file grid types.
        $this->addColumn(new FileNameGridColumn($capabilities->canViewNotes(), $this->getStageId()));

        // Additional column with file upload date/creation date
        $this->addColumn(new FileDateGridColumn($capabilities->canViewNotes()));

        // Set the no items row text
        $this->setEmptyRowText('grid.noFiles');
    }

    /**
     * @copyDoc GridHandler::getFilterForm()
     */
    protected function getFilterForm()
    {
        return 'controllers/grid/files/filesGridFilter.tpl';
    }

    /**
     * @copyDoc GridHandler::renderFilter()
     */
    public function renderFilter($request, $filterData = [])
    {
        return parent::renderFilter(
            $request,
            [
                'columns' => $this->getFilterColumns(),
                'gridId' => $this->getId()
            ]
        );
    }

    /**
     * @copyDoc GridHandler::getFilterSelectionData()
     */
    public function getFilterSelectionData($request)
    {
        return [
            'search' => (string) $request->getUserVar('search'),
            'column' => (string) $request->getUserVar('column'),
        ];
    }

    /**
     * Get which columns can be used by users to filter data.
     *
     * @return array
     */
    protected function getFilterColumns()
    {
        return [
            'name' => __('common.name'),
        ];
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
    // Protected methods.
    //
    public function getFilesToDownload($request)
    {
        return $this->getGridDataElements($request);
    }
}
