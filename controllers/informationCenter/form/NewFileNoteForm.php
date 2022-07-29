<?php

/**
 * @file controllers/informationCenter/form/NewFileNoteForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NewFileNoteForm
 * @ingroup informationCenter_form
 *
 * @brief Form to display and post notes on a file
 */

namespace PKP\controllers\informationCenter\form;

use APP\template\TemplateManager;

class NewFileNoteForm extends NewNoteForm
{
    /** @var int The ID of the submission file to attach the note to */
    public $fileId;

    /**
     * Constructor.
     */
    public function __construct($fileId)
    {
        parent::__construct();

        $this->fileId = $fileId;
    }

    /**
     * Return the assoc type for this note.
     *
     * @return int
     */
    public function getAssocType()
    {
        return ASSOC_TYPE_SUBMISSION_FILE;
    }

    /**
     * Return the submit note button locale key.
     * Can be overriden by subclasses.
     *
     * @return string
     */
    public function getSubmitNoteLocaleKey()
    {
        return 'informationCenter.addNote';
    }

    /**
     * Return the assoc ID for this note.
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->fileId;
    }

    /**
     * @copydoc NewFileNoteForm::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('showEarlierEntries', true);
        return parent::fetch($request, $template, $display);
    }
}
