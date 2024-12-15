<?php

/**
 * @file controllers/grid/settings/library/form/NewLibraryFileForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NewLibraryFileForm
 *
 * @ingroup controllers_grid_file_form
 *
 * @brief Form for adding/editing a file
 * stores/retrieves from an associative array
 */

namespace PKP\controllers\grid\settings\library\form;

use APP\core\Application;
use APP\file\LibraryFileManager;
use PKP\context\LibraryFileDAO;
use PKP\controllers\grid\files\form\LibraryFileForm;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileDAO;
use PKP\file\TemporaryFileManager;

class NewLibraryFileForm extends LibraryFileForm
{
    /**
     * Constructor.
     *
     * @param int $contextId
     */
    public function __construct(int $contextId)
    {
        parent::__construct('controllers/grid/settings/library/form/newFileForm.tpl', $contextId);
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'temporaryFileId', 'required', 'settings.libraryFiles.fileRequired'));
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
     * @copydoc Form::execute()
     *
     * @return int The new library file id.
     */
    public function execute(...$functionArgs)
    {
        $userId = Application::get()->getRequest()->getUser()->getId();

        // Fetch the temporary file storing the uploaded library file
        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */
        $temporaryFile = $temporaryFileDao->getTemporaryFile(
            $this->getData('temporaryFileId'),
            $userId
        );
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $libraryFileManager = new LibraryFileManager($this->contextId);

        // Convert the temporary file to a library file and store
        $libraryFile = $libraryFileManager->copyFromTemporaryFile($temporaryFile, $this->getData('fileType'));
        assert(isset($libraryFile));
        $libraryFile->setContextId($this->contextId);
        $libraryFile->setName($this->getData('libraryFileName'), null); // Localized
        $libraryFile->setData('description', $this->getData('description'), null); // Localized
        $libraryFile->setType($this->getData('fileType'));
        $libraryFile->setPublicAccess($this->getData('publicAccess'));

        $fileId = $libraryFileDao->insertObject($libraryFile);

        // Clean up the temporary file
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFileManager->deleteById($this->getData('temporaryFileId'), $userId);
        parent::execute(...$functionArgs);
        return $fileId;
    }
}
