<?php

/**
 * @file controllers/grid/files/proof/form/ManageProofFilesForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageProofFilesForm
 * @ingroup controllers_grid_files_proof
 *
 * @brief Form to add files to the proof files grid
 */

namespace PKP\controllers\grid\files\proof\form;

use APP\template\TemplateManager;
use PKP\controllers\grid\files\form\ManageSubmissionFilesForm;
use PKP\submissionFile\SubmissionFile;

class ManageProofFilesForm extends ManageSubmissionFilesForm
{
    /** @var int Representation ID. */
    public $_representationId;

    /**
     * Constructor.
     *
     * @param int $submissionId Submission ID.
     * @param int $publicationId Publication ID
     * @param int $representationId Representation ID.
     */
    public function __construct($submissionId, $publicationId, $representationId)
    {
        parent::__construct($submissionId, 'controllers/grid/files/proof/manageProofFiles.tpl');
        $this->_publicationId = $publicationId;
        $this->_representationId = $representationId;
    }


    //
    // Overridden template methods
    //
    /**
     * @copydoc ManageSubmissionFilesForm::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('publicationId', $this->_publicationId);
        $templateMgr->assign('representationId', $this->_representationId);
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc ManageSubmissionFilesForm::fileExistsInStage
     */
    protected function fileExistsInStage($submissionFile, $stageSubmissionFiles, $fileStage)
    {
        return false;
    }


    /**
     * @copydoc ManageSubmissionFilesForm::importFile()
     */
    protected function importFile($submissionFile, $fileStage)
    {
        $newSubmissionFile = clone $submissionFile;
        $newSubmissionFile->setData('assocType', ASSOC_TYPE_REPRESENTATION);
        $newSubmissionFile->setData('assocId', $this->_representationId);
        $newSubmissionFile->setData('viewable', false); // Not approved by default

        return parent::importFile($newSubmissionFile, SubmissionFile::SUBMISSION_FILE_PROOF);
    }
}
