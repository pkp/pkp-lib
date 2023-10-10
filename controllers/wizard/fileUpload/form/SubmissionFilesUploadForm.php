<?php

/**
 * @file controllers/wizard/fileUpload/form/SubmissionFilesUploadForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesUploadForm
 *
 * @ingroup controllers_wizard_fileUpload_form
 *
 * @brief Form for adding/editing a submission file
 */

namespace PKP\controllers\wizard\fileUpload\form;

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\form\validation\FormValidator;
use PKP\submission\Genre;
use PKP\submission\GenreDAO;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class SubmissionFilesUploadForm extends PKPSubmissionFilesUploadBaseForm
{
    /** @var array */
    public $_uploaderRoles;

    /** @var Submission */
    protected $_submission;

    /**
     * Constructor.
     *
     * @param Request $request
     * @param int $submissionId
     * @param int $stageId One of the WORKFLOW_STAGE_ID_* constants.
     * @param array $uploaderRoles
     * @param int $fileStage
     * @param bool $revisionOnly
     * @param int $stageId
     * @param ReviewRound $reviewRound
     * @param int $revisedFileId
     * @param int $assocType
     * @param int $assocId
     * @param int $queryId
     */
    public function __construct(
        $request,
        $submissionId,
        $stageId,
        $uploaderRoles,
        $fileStage,
        $revisionOnly = false,
        $reviewRound = null,
        $revisedFileId = null,
        $assocType = null,
        $assocId = null,
        $queryId = null
    ) {
        // Initialize class.
        assert(is_null($uploaderRoles) || (is_array($uploaderRoles) && count($uploaderRoles) >= 1));
        $this->_uploaderRoles = $uploaderRoles;

        parent::__construct(
            $request,
            'controllers/wizard/fileUpload/form/fileUploadForm.tpl',
            $submissionId,
            $stageId,
            $fileStage,
            $revisionOnly,
            $reviewRound,
            $revisedFileId,
            $assocType,
            $assocId,
            $queryId
        );

        $this->_submission = Repo::submission()->get($submissionId);

        // Disable the genre selector for review file attachments
        if ($fileStage == SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT) {
            $this->setData('isReviewAttachment', true);
        }
    }


    //
    // Getters and Setters
    //
    /**
     * Get the uploader roles.
     *
     * @return array
     */
    public function getUploaderRoles()
    {
        assert(!is_null($this->_uploaderRoles));
        return $this->_uploaderRoles;
    }


    //
    // Implement template methods from Form
    //
    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['genreId']);
        return parent::readInputData();
    }

    /**
     * @copydoc Form::validate()
     */
    public function validate($callHooks = true)
    {
        // Is this a revision?
        $revisedFileId = $this->getRevisedFileId();
        if ($this->getData('revisionOnly')) {
            assert($revisedFileId > 0);
        }

        // Retrieve the request context.
        $request = Application::get()->getRequest();
        $router = $request->getRouter();
        $context = $router->getContext($request);
        if (
            $this->getData('fileStage') != SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT and
            !$revisedFileId
        ) {
            // Add an additional check for the genre to the form.
            $this->addCheck(new \PKP\form\validation\FormValidatorCustom(
                $this,
                'genreId',
                FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
                'submission.upload.noGenre',
                function ($genreId) use ($context) {
                    $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
                    return $genreDao->getById($genreId, $context->getId()) instanceof Genre;
                }
            ));
        }

        return parent::validate($callHooks);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        // Retrieve available submission file genres.
        $genreList = $this->_retrieveGenreList($request);
        $this->setData('submissionFileGenres', $genreList);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Save the submission file upload form.
     *
     * @see Form::execute()
     *
     * @return ?SubmissionFile if successful, otherwise null
     */
    public function execute(...$functionParams)
    {
        // Identify the uploading user.
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        assert($user instanceof User);

        // Upload the file.
        $fileManager = new FileManager();
        $extension = $fileManager->parseFileExtension($_FILES['uploadedFile']['name']);

        $submissionDir = Repo::submissionFile()->getSubmissionDir($request->getContext()->getId(), $this->getData('submissionId'));
        $fileId = Services::get('file')->add(
            $_FILES['uploadedFile']['tmp_name'],
            $submissionDir . '/' . uniqid() . '.' . $extension
        );

        if ($this->getRevisedFileId()) {
            $submissionFile = Repo::submissionFile()->get($this->getRevisedFileId());
            Repo::submissionFile()->edit(
                $submissionFile,
                [
                    'fileId' => $fileId,
                    'name' => [
                        $this->_submission->getLocale() => $_FILES['uploadedFile']['name'],
                    ],
                    'uploaderUserId' => $user->getId(),
                ]
            );

            $submissionFile = Repo::submissionFile()->get($this->getRevisedFileId());
        } else {
            $submissionFile = Repo::submissionFile()->dao->newDataObject();
            $submissionFile->setData('fileId', $fileId);
            $submissionFile->setData('fileStage', $this->getData('fileStage'));
            $submissionFile->setData('name', $_FILES['uploadedFile']['name'], $this->_submission->getLocale());
            $submissionFile->setData('submissionId', $this->getData('submissionId'));
            $submissionFile->setData('uploaderUserId', $user->getId());
            $submissionFile->setData('assocType', $this->getData('assocType') ? (int) $this->getData('assocType') : null);
            $submissionFile->setData('assocId', $this->getData('assocId') ? (int) $this->getData('assocId') : null);

            $genreId = (int) $this->getData('genreId');
            if ($genreId === 0) {
                $genreId = null;
            }
            $submissionFile->setData('genreId', $genreId);

            if ($this->getReviewRound() && $this->getReviewRound()->getId() && empty($submissionFile->getData('assocType'))) {
                $submissionFile->setData('assocType', Application::ASSOC_TYPE_REVIEW_ROUND);
                $submissionFile->setData('assocId', $this->getReviewRound()->getId());
            }

            $id = Repo::submissionFile()->add($submissionFile);

            $submissionFile = Repo::submissionFile()->get($id);
        }

        if (!$submissionFile) {
            return null;
        }

        $hookResult = parent::execute($submissionFile, ...$functionParams);
        if ($hookResult) {
            return $hookResult;
        }

        return $submissionFile;
    }


    //
    // Private helper methods
    //
    /**
     * Retrieve the genre list.
     *
     * @param Request $request
     *
     * @return array
     */
    public function _retrieveGenreList($request)
    {
        $context = $request->getContext();
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $dependentFilesOnly = $request->getUserVar('dependentFilesOnly') ? true : false;
        $genres = $genreDao->getByDependenceAndContextId($dependentFilesOnly, $context->getId());

        // Transform the genres into an array and
        // assign them to the form.
        $genreList = [];
        while ($genre = $genres->next()) {
            $genreList[$genre->getId()] = $genre->getLocalizedName();
        }
        return $genreList;
    }
}
