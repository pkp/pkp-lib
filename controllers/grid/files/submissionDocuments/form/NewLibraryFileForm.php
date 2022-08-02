<?php
/**
 * @file controllers/grid/files/submissionDocuments/form/NewLibraryFileForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileForm
 * @ingroup controllers_grid_files_submissionDocuments_form
 *
 * @brief Form for adding/edditing a file
 * stores/retrieves from an associative array
 */

namespace PKP\controllers\grid\files\submissionDocuments\form;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\controllers\grid\files\form\LibraryFileForm;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;

class NewLibraryFileForm extends LibraryFileForm
{
    /** @var int */
    public $submissionId;

    /**
     * Constructor.
     *
     * @param int $contextId
     */
    public function __construct($contextId, $submissionId)
    {
        parent::__construct('controllers/grid/files/submissionDocuments/form/newFileForm.tpl', $contextId);
        $this->submissionId = $submissionId;
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'temporaryFileId', 'required', 'settings.libraryFiles.fileRequired'));
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['temporaryFileId', 'submissionId']);
        return parent::readInputData();
    }

    /**
     * @copydoc LibraryFileForm::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('submissionId', $this->getSubmissionId());
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     *
     * @return $fileId int The new library file id.
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
        $libraryFile = & $libraryFileManager->copyFromTemporaryFile($temporaryFile, $this->getData('fileType'));
        assert(isset($libraryFile));
        $libraryFile->setContextId($this->contextId);
        $libraryFile->setName($this->getData('libraryFileName'), null); // Localized
        $libraryFile->setType($this->getData('fileType'));
        $libraryFile->setSubmissionId($this->getData('submissionId'));

        $fileId = $libraryFileDao->insertObject($libraryFile);

        // Clean up the temporary file
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFileManager->deleteById($this->getData('temporaryFileId'), $userId);

        parent::execute(...$functionArgs);

        return $fileId;
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
