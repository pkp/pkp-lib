<?php

/**
 * @file controllers/grid/settings/library/form/EditLibraryFileForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditLibraryFileForm
 *
 * @ingroup controllers_grid_file_form
 *
 * @brief Form for editing a library file
 */

namespace PKP\controllers\grid\settings\library\form;

use APP\core\Application;
use APP\file\LibraryFileManager;
use PKP\context\LibraryFile;
use PKP\context\LibraryFileDAO;
use PKP\controllers\grid\files\form\LibraryFileForm;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;

class EditLibraryFileForm extends LibraryFileForm
{
    /** The file being edited, or null for new */
    public ?LibraryFile $libraryFile;

    /**
     * Constructor.
     *
     * @param int $fileId optional
     */
    public function __construct(int $contextId, $fileId)
    {
        parent::__construct('controllers/grid/settings/library/form/editFileForm.tpl', $contextId);
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $this->libraryFile = $libraryFileDao->getById($fileId);

        if (!$this->libraryFile || $this->libraryFile->getContextId() != $this->contextId) {
            throw new \Exception('Invalid library file!');
        }
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData(): void
    {
        $this->readUserVars(['temporaryFileId']);
        parent::readInputData();
    }

    /**
     * Initialize form data from current settings.
     */
    public function initData(): void
    {
        $this->_data = [
            'libraryFileName' => $this->libraryFile->getName(null), // Localized
            'libraryFile' => $this->libraryFile, // For read-only info
            'publicAccess' => $this->libraryFile->getPublicAccess() ? true : false,
            'temporaryFileId' => null,
        ];
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $userId = Application::get()->getRequest()->getUser()->getId();

        // Fetch the temporary file storing the uploaded library file
        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /* @var \PKP\file\TemporaryFileDAO $temporaryFileDao*/
        $temporaryFile = $temporaryFileDao->getTemporaryFile(
            $this->getData('temporaryFileId'),
            $userId
        );
        if ($temporaryFile) {
            $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /* @var LibraryFileDAO $libraryFileDao */
            $libraryFileManager = new LibraryFileManager($this->contextId);

            // Convert the temporary file to a library file and store
            $this->libraryFile = $libraryFileManager->replaceFromTemporaryFile($temporaryFile, $this->getData('fileType'), $this->libraryFile);
            // Clean up the temporary file
            $temporaryFileManager = new TemporaryFileManager();
            $temporaryFileManager->deleteById($this->getData('temporaryFileId'), $userId);
        }
        $this->libraryFile->setName($this->getData('libraryFileName'), null); // Localized
        $this->libraryFile->setType($this->getData('fileType'));
        $this->libraryFile->setPublicAccess($this->getData('publicAccess'));

        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $libraryFileDao->updateObject($this->libraryFile);
        parent::execute(...$functionArgs);
    }
}
