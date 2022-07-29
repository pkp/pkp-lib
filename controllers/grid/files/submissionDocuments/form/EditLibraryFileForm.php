<?php

/**
 * @file controllers/grid/files/submissionDocuments/form/EditLibraryFileForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditLibraryFileForm
 * @ingroup controllers_grid_files_submissionDocuments_form
 *
 * @brief Form for editing a library file
 */

namespace PKP\controllers\grid\files\submissionDocuments\form;

use PKP\controllers\grid\files\form\LibraryFileForm;
use PKP\db\DAORegistry;

class EditLibraryFileForm extends LibraryFileForm
{
    /** @var LibraryFile the file being edited, or null for new */
    public $libraryFile;

    /** @var int the id of the submission for this library file */
    public $submissionId;

    /**
     * Constructor.
     *
     * @param int $contextId
     * @param int $fileId optional
     */
    public function __construct($contextId, $fileId, $submissionId)
    {
        parent::__construct('controllers/grid/files/submissionDocuments/form/editFileForm.tpl', $contextId);

        $this->submissionId = $submissionId;
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $this->libraryFile = $libraryFileDao->getById($fileId);

        if (!$this->libraryFile || $this->libraryFile->getContextId() != $this->contextId || $this->libraryFile->getSubmissionId() != $this->getSubmissionId()) {
            fatalError('Invalid library file!');
        }
    }

    /**
     * Initialize form data from current settings.
     */
    public function initData()
    {
        $this->_data = [
            'submissionId' => $this->libraryFile->getSubmissionId(),
            'libraryFileName' => $this->libraryFile->getName(null), // Localized
            'libraryFile' => $this->libraryFile // For read-only info
        ];
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $this->libraryFile->setName($this->getData('libraryFileName'), null); // Localized
        $this->libraryFile->setType($this->getData('fileType'));

        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $libraryFileDao->updateObject($this->libraryFile);

        parent::execute(...$functionArgs);
    }

    /**
     * return the submission ID for this library file.
     *
     * @return int
     */
    public function getSubmissionId()
    {
        return $this->submissionId;
    }
}
