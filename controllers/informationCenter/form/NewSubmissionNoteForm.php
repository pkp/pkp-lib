<?php

/**
 * @file controllers/informationCenter/form/NewSubmissionNoteForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NewSubmissionNoteForm
 * @ingroup informationCenter_form
 *
 * @brief Form to display and post notes on a file
 */

namespace PKP\controllers\informationCenter\form;

class NewSubmissionNoteForm extends NewNoteForm
{
    /** @var int The ID of the submission to attach the note to */
    public $submissionId;

    /**
     * Constructor.
     */
    public function __construct($submissionId)
    {
        parent::__construct();

        $this->submissionId = $submissionId;
    }

    /**
     * Return the assoc type for this note.
     *
     * @return int
     */
    public function getAssocType()
    {
        return ASSOC_TYPE_SUBMISSION;
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
        return $this->submissionId;
    }
}
