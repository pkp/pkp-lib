<?php

/**
 * @file controllers/grid/settings/library/form/EditLibraryFileForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditLibraryFileForm
 * @ingroup controllers_grid_file_form
 *
 * @brief Form for editing a library file
 */

namespace PKP\controllers\grid\settings\library\form;

use PKP\db\DAORegistry;
use PKP\controllers\grid\files\form\LibraryFileForm;

class EditLibraryFileForm extends LibraryFileForm
{
    /** @var LibraryFile the file being edited, or null for new */
    public $libraryFile;

    /** @var int the id of the context this library file is attached to */
    public $contextId;

    /**
     * Constructor.
     *
     * @param int $contextId
     * @param int $fileId optional
     */
    public function __construct($contextId, $fileId)
    {
        parent::__construct('controllers/grid/settings/library/form/editFileForm.tpl', $contextId);
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $this->libraryFile = $libraryFileDao->getById($fileId);

        if (!$this->libraryFile || $this->libraryFile->getContextId() != $this->contextId) {
            fatalError('Invalid library file!');
        }
    }

    /**
     * Initialize form data from current settings.
     */
    public function initData()
    {
        $this->_data = [
            'libraryFileName' => $this->libraryFile->getName(null), // Localized
            'libraryFile' => $this->libraryFile, // For read-only info
            'publicAccess' => $this->libraryFile->getPublicAccess() ? true : false,
        ];
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $this->libraryFile->setName($this->getData('libraryFileName'), null); // Localized
        $this->libraryFile->setType($this->getData('fileType'));
        $this->libraryFile->setPublicAccess($this->getData('publicAccess'));

        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $libraryFileDao->updateObject($this->libraryFile);
        parent::execute(...$functionArgs);
    }
}
