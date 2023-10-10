<?php

/**
 * @file controllers/grid/files/SubmissionFilesGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesGridRow
 *
 * @ingroup controllers_grid_files
 *
 * @brief Handle submission file grid row requests.
 */

namespace PKP\controllers\grid\files;

use PKP\controllers\api\file\linkAction\DeleteFileLinkAction;
use PKP\controllers\api\file\linkAction\EditFileLinkAction;
use PKP\controllers\grid\GridRow;
use PKP\controllers\informationCenter\linkAction\FileInfoCenterLinkAction;
use PKP\submissionFile\SubmissionFile;

class SubmissionFilesGridRow extends GridRow
{
    /** @var FilesGridCapabilities */
    public $_capabilities;

    /** @var int */
    public $_stageId;

    /**
     * Constructor
     *
     * @param FilesGridCapabilities $capabilities
     * @param int $stageId Stage ID (optional)
     */
    public function __construct($capabilities = null, $stageId = null)
    {
        $this->_capabilities = $capabilities;
        $this->_stageId = $stageId;
        parent::__construct();
    }


    //
    // Getters and Setters
    //
    /**
     * Can the user delete files from this grid?
     *
     * @return bool
     */
    public function canDelete()
    {
        return $this->_capabilities->canDelete();
    }

    /**
     * Can the user view file notes on this grid?
     *
     * @return bool
     */
    public function canViewNotes()
    {
        return $this->_capabilities->canViewNotes();
    }

    /**
     * Can the user manage files in this grid?
     *
     * @return bool
     */
    public function canEdit()
    {
        return $this->_capabilities->canEdit();
    }

    /**
     * Get the stage id, if any.
     *
     * @return int Stage ID
     */
    public function getStageId()
    {
        return $this->_stageId;
    }

    //
    // Overridden template methods from GridRow
    //
    /**
     * @copydoc GridRow::initialize()
     */
    public function initialize($request, $template = 'controllers/grid/gridRow.tpl')
    {
        parent::initialize($request, $template);

        // Retrieve the submission file.
        $submissionFileData = & $this->getData();
        assert(isset($submissionFileData['submissionFile']));
        $submissionFile = & $submissionFileData['submissionFile']; /** @var SubmissionFile $submissionFile */
        assert($submissionFile instanceof SubmissionFile);

        // File grid row actions:
        // 1) Information center action.
        if ($this->canViewNotes()) {
            $this->addAction(new FileInfoCenterLinkAction($request, $submissionFile, $this->getStageId()));
        }

        // 2) Edit metadata action.
        if ($this->canEdit()) {
            $this->addAction(new EditFileLinkAction($request, $submissionFile, $this->getStageId()));
        }

        // 3) Delete file action.
        if ($this->canDelete()) {
            $this->addAction(new DeleteFileLinkAction($request, $submissionFile, $this->getStageId()));
        }
    }
}
