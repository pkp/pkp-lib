<?php

/**
 * @file controllers/informationCenter/form/NewGalleyNoteForm.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NewGalleyNoteForm
 *
 * @brief Form to display and post notes on a galley
 */

namespace PKP\controllers\informationCenter\form;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\controllers\informationCenter\form\NewNoteForm;

class NewGalleyNoteForm extends NewNoteForm
{
    /** 
     * The ID of the galley to attach the note to
     */
    public int $galleyId;

    /**
     * Constructor.
     */
    public function __construct(int $galleyId)
    {
        parent::__construct();
        $this->galleyId = $galleyId;
    }

    /**
     * Return the assoc type for this note.
     *
     * @return int
     */
    public function getAssocType()
    {
        return Application::ASSOC_TYPE_REPRESENTATION;
    }

    /**
     * Return the submit note button locale key.
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
        return $this->galleyId;
    }

    /**
     * @copydoc NewNoteForm::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('showEarlierEntries', false);
        
        return parent::fetch($request, $template, $display);
    }
}
