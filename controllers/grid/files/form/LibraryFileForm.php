<?php

/**
 * @file controllers/grid/files/form/LibraryFileForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileForm
 *
 * @ingroup controllers_grid_file_form
 *
 * @brief Form for adding/editing a file
 */

namespace PKP\controllers\grid\files\form;

use APP\file\LibraryFileManager;
use APP\template\TemplateManager;
use PKP\form\Form;

class LibraryFileForm extends Form
{
    /** the library file manager instantiated in this form. */
    public LibraryFileManager $libraryFileManager;

    /**
     * Constructor.
     *
     * @param string $template
     * @param int $contextId The id of the context this library file is attached to
     */
    public function __construct($template, public int $contextId)
    {
        parent::__construct($template);
        $this->libraryFileManager = $libraryFileManager = new LibraryFileManager($contextId);

        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'libraryFileName', 'required', 'settings.libraryFiles.nameRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom(
            $this,
            'fileType',
            'required',
            'settings.libraryFiles.typeRequired',
            function ($type) use ($libraryFileManager) {
                return is_numeric($type) && $libraryFileManager->getNameFromType($type);
            }
        ));

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false): ?string
    {
        // load the file types for the selector on the form.
        $templateMgr = TemplateManager::getManager($request);
        $fileTypeKeys = $this->libraryFileManager->getTypeTitleKeyMap();
        $templateMgr->assign('fileTypes', $fileTypeKeys);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData(): void
    {
        $this->readUserVars(['libraryFileName', 'description', 'fileType', 'publicAccess']);
    }
}
