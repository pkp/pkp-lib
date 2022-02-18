<?php

/**
 * @file controllers/wizard/fileUpload/form/SubmissionFilesMetadataForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesMetadataForm
 * @ingroup controllers_wizard_fileUpload_form
 *
 * @brief Form for editing a submission file's metadata
 */

use APP\facades\Repo;
use APP\template\TemplateManager;

use PKP\form\Form;
use PKP\submissionFile\SubmissionFile;

class SubmissionFilesMetadataForm extends Form
{
    /** @var SubmissionFile */
    public $_submissionFile;

    /** @var int */
    public $_stageId;

    /** @var ReviewRound */
    public $_reviewRound;

    /**
     * Constructor.
     *
     * @param SubmissionFile $submissionFile
     * @param int $stageId One of the WORKFLOW_STAGE_ID_* constants.
     * @param ReviewRound $reviewRound (optional) Current review round, if any.
     * @param string $template Path and filename to template file (optional).
     */
    public function __construct($submissionFile, $stageId, $reviewRound = null, $template = null)
    {
        if ($template === null) {
            $template = 'controllers/wizard/fileUpload/form/submissionFileMetadataForm.tpl';
        }
        parent::__construct($template);

        // Initialize the object.
        $this->_submissionFile = $submissionFile;
        $this->_stageId = $stageId;
        if (is_a($reviewRound, 'ReviewRound')) {
            $this->_reviewRound = $reviewRound;
        }

        $submissionLocale = $submissionFile->getData('locale');
        $this->setDefaultFormLocale($submissionLocale);

        // Add validation checks.
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'name', 'required', 'submission.submit.fileNameRequired', $submissionLocale));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }


    //
    // Getters and Setters
    //
    /**
     * Get the submission file.
     *
     * @return SubmissionFile
     */
    public function getSubmissionFile()
    {
        return $this->_submissionFile;
    }

    /**
     * Get the workflow stage id.
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->_stageId;
    }

    /**
     * Get review round.
     *
     * @return ReviewRound
     */
    public function getReviewRound()
    {
        return $this->_reviewRound;
    }

    /**
     * Set the "show buttons" flag
     *
     * @param bool $showButtons
     */
    public function setShowButtons($showButtons)
    {
        $this->setData('showButtons', $showButtons);
    }

    /**
     * Get the "show buttons" flag
     *
     * @return bool
     */
    public function getShowButtons()
    {
        return $this->getData('showButtons');
    }


    //
    // Implement template methods from Form
    //
    /**
     * @copydoc Form::getLocaleFieldNames()
     */
    public function getLocaleFieldNames()
    {
        return ['name'];
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['name', 'showButtons',
            'artworkCaption', 'artworkCredit', 'artworkCopyrightOwner',
            'artworkCopyrightOwnerContact', 'artworkPermissionTerms',
            'creator', 'subject', 'description', 'publisher', 'sponsor', 'source', 'language', 'dateCreated',
        ]);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $reviewRound = $this->getReviewRound();
        $genre = DAORegistry::getDAO('GenreDAO')->getById($this->getSubmissionFile()->getData('genreId'), $request->getContext()->getId());

        $templateMgr->assign([
            'submissionFile' => $this->getSubmissionFile(),
            'stageId' => $this->getStageId(),
            'reviewRoundId' => $reviewRound ? $reviewRound->getId() : null,
            'supportsDependentFiles' => Repo::submissionFile()->supportsDependentFiles($this->getSubmissionFile()),
            'genre' => $genre,
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionParams)
    {
        $props = [
            'name' => $this->getData('name'),
        ];

        // Artwork metadata
        $props = array_merge($props, [
            'caption' => $this->getData('artworkCaption'),
            'credit' => $this->getData('artworkCredit'),
            'copyrightOwner' => $this->getData('artworkCopyrightOwner'),
            'terms' => $this->getData('artworkPermissionTerms'),
        ]);

        // Supplementary file metadata
        $props = array_merge($props, [
            'subject' => $this->getData('subject'),
            'creator' => $this->getData('creator'),
            'description' => $this->getData('description'),
            'publisher' => $this->getData('publisher'),
            'sponsor' => $this->getData('sponsor'),
            'source' => $this->getData('source'),
            'language' => $this->getData('language'),
            'dateCreated' => $this->getData('dateCreated'),
        ]);

        Repo::submissionFile()->edit($this->getSubmissionFile(), $props);
        $this->_submissionFile = Repo::submissionFile()->get(
            $this->getSubmissionFile()->getId()
        );

        parent::execute(...$functionParams);
    }
}
