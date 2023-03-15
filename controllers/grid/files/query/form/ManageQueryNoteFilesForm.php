<?php

/**
 * @file controllers/grid/files/query/form/ManageQueryNoteFilesForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageQueryNoteFilesForm
 * @ingroup controllers_grid_files_query
 *
 * @brief Form to add files to the query files grid
 */

namespace PKP\controllers\grid\files\query\form;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\controllers\grid\files\form\ManageSubmissionFilesForm;
use PKP\submissionFile\SubmissionFile;

class ManageQueryNoteFilesForm extends ManageSubmissionFilesForm
{
    /** @var int Query ID */
    public $_queryId;

    /** @var int Note ID */
    public $_noteId;

    /** @var array Extra parameters to actions. */
    public $_actionArgs;

    /**
     * Constructor.
     *
     * @param int $submissionId Submission ID.
     * @param int $queryId Query ID.
     * @param int $noteId Note ID.
     * @param array $actionArgs Optional list of extra request parameters.
     */
    public function __construct($submissionId, $queryId, $noteId, $actionArgs = [])
    {
        parent::__construct($submissionId, 'controllers/grid/files/query/manageQueryNoteFiles.tpl');
        $this->_queryId = $queryId;
        $this->_noteId = $noteId;
        $this->_actionArgs = $actionArgs;
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'queryId' => $this->_queryId,
            'noteId' => $this->_noteId,
            'actionArgs' => $this->_actionArgs,
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Save selection of query files
     *
     * @param array $stageSubmissionFiles The list of submission files in the stage.
     * @param int $fileStage SubmissionFile::SUBMISSION_FILE_...
     */
    public function execute($stageSubmissionFiles = null, $fileStage = null, ...$functionArgs)
    {
        parent::execute($stageSubmissionFiles, SubmissionFile::SUBMISSION_FILE_QUERY);
    }

    /**
     * @copydoc ManageSubmissionFilesForm::fileExistsInStage
     */
    protected function fileExistsInStage($submissionFile, $stageSubmissionFiles, $fileStage)
    {
        if (!parent::fileExistsInStage($submissionFile, $stageSubmissionFiles, $fileStage)) {
            return false;
        }
        foreach ($stageSubmissionFiles[$submissionFile->getId()] as $stageFile) {
            if (
                $stageFile->getFileStage() == $submissionFile->getFileStage() &&
                $stageFile->getFileStage() == $fileStage &&
                ($stageFile->getData('assocType') != Application::ASSOC_TYPE_NOTE || $stageFile->getData('assocId') == $this->_noteId)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @copydoc ManageSubmissionFilesForm::importFile()
     */
    protected function importFile($submissionFile, $fileStage)
    {
        $newSubmissionFile = clone $submissionFile;
        $newSubmissionFile->setData('assocType', Application::ASSOC_TYPE_NOTE);
        $newSubmissionFile->setData('assocId', $this->_noteId);

        return parent::importFile($newSubmissionFile, $fileStage);
    }
}
