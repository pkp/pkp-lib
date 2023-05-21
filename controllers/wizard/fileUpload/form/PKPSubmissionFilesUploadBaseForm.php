<?php

/**
 * @file controllers/wizard/fileUpload/form/PKPSubmissionFilesUploadBaseForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFilesUploadBaseForm
 *
 * @ingroup controllers_wizard_fileUpload_form
 *
 * @brief Form for adding/editing a submission file
 */

namespace PKP\controllers\wizard\fileUpload\form;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use Exception;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\ConfirmationModal;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class PKPSubmissionFilesUploadBaseForm extends Form
{
    /** @var int */
    public $_stageId;

    /** @var ReviewRound */
    public $_reviewRound;

    /** @var array the submission files for this submission and file stage */
    public $_submissionFiles;

    /**
     * Constructor.
     *
     * @param Request $request
     * @param string $template
     * @param int $submissionId
     * @param int $stageId One of the WORKFLOW_STAGE_ID_* constants.
     * @param int $fileStage
     * @param bool $revisionOnly
     * @param ReviewRound $reviewRound
     * @param int $revisedFileId
     * @param int $assocType
     * @param int $assocId
     * @param int $queryId
     */
    public function __construct(
        $request,
        $template,
        $submissionId,
        $stageId,
        $fileStage,
        $revisionOnly = false,
        $reviewRound = null,
        $revisedFileId = null,
        $assocType = null,
        $assocId = null,
        $queryId = null
    ) {
        // Check the incoming parameters.
        if (!is_numeric($submissionId) || $submissionId <= 0 ||
            !is_numeric($fileStage) || $fileStage <= 0 ||
            !is_numeric($stageId) || $stageId < 1 || $stageId > 5 ||
            isset($assocType) !== isset($assocId)) {
            fatalError('Invalid parameters!');
        }

        // Initialize class.
        parent::__construct($template);
        $this->_stageId = $stageId;

        if ($reviewRound) {
            $this->_reviewRound = & $reviewRound;
        } elseif ($assocType == Application::ASSOC_TYPE_REVIEW_ASSIGNMENT && !$reviewRound) {
            // Get the review assignment object.
            /** @var ReviewAssignmentDAO */
            $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
            /** @var \PKP\submission\reviewAssignment\ReviewAssignment */
            $reviewAssignment = $reviewAssignmentDao->getById((int) $assocId);
            if ($reviewAssignment->getDateCompleted()) {
                fatalError('Review already completed!');
            }

            // Get the review round object.
            /** @var ReviewRound */
            $reviewRoundDao = DAORegistry::getDAO('ReviewRound');
            $this->_reviewRound = $reviewRoundDao->getById($reviewAssignment->getReviewRoundId());
        } elseif (!$assocType && !$reviewRound) {
            $reviewRound = null;
        }

        $this->setData('fileStage', (int)$fileStage);
        $this->setData('submissionId', (int)$submissionId);
        $this->setData('revisionOnly', (bool)$revisionOnly);
        $this->setData('revisedFileId', $revisedFileId ? (int)$revisedFileId : null);
        $this->setData('reviewRoundId', $reviewRound ? $reviewRound->getId() : null);
        $this->setData('assocType', $assocType ? (int)$assocType : null);
        $this->setData('assocId', $assocId ? (int)$assocId : null);
        $this->setData('queryId', $queryId ? (int) $queryId : null);

        // Add validators.
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
    }


    //
    // Setters and Getters
    //
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
     * Get the review round object (if any).
     *
     * @return ReviewRound
     */
    public function getReviewRound()
    {
        return $this->_reviewRound;
    }

    /**
     * Get the revised file id (if any).
     *
     * @return int the revised file id
     */
    public function getRevisedFileId()
    {
        return $this->getData('revisedFileId') ? (int)$this->getData('revisedFileId') : null;
    }

    /**
     * Get the associated type
     *
     * @return int
     */
    public function getAssocType()
    {
        return $this->getData('assocType');
    }

    /**
     * Get the associated id.
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getData('assocId');
    }

    /**
     * Get the submission files belonging to the
     * submission and to the file stage.
     *
     * @return array a list of SubmissionFile instances.
     */
    public function getSubmissionFiles()
    {
        if (is_null($this->_submissionFiles)) {
            if ($this->getStageId() == WORKFLOW_STAGE_ID_INTERNAL_REVIEW || $this->getStageId() == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
                // If we have a review stage id then we also expect a review round.
                if (!$this->getData('fileStage') == SubmissionFile::SUBMISSION_FILE_QUERY && !is_a($this->getReviewRound(), 'ReviewRound')) {
                    throw new Exception('Can not request submission files for a review stage without specifying a review round.');
                }
                // Can only upload submission files, review files, review attachments, dependent files, or query attachments.
                if (!in_array($this->getData('fileStage'), [
                    SubmissionFile::SUBMISSION_FILE_SUBMISSION,
                    SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
                    SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE,
                    SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT,
                    SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
                    SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION,
                    SubmissionFile::SUBMISSION_FILE_QUERY,
                    SubmissionFile::SUBMISSION_FILE_DEPENDENT,
                    SubmissionFile::SUBMISSION_FILE_ATTACHMENT,
                ])) {
                    throw new Exception('The file stage is not valid for the review stage.');
                }

                // Hide the revision selector for review
                // attachments to make it easier for reviewers
                $reviewRound = $this->getReviewRound();
                if ($this->getData('fileStage') == SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT) {
                    $this->_submissionFiles = [];
                } elseif ($reviewRound) {
                    // Retrieve the submission files for the given review round.
                    $submissionId = (int) $this->getData('submissionId');
                    $submission = Repo::submission()->get($submissionId);
                    if ($submission->getData('contextId') !== Application::get()->getRequest()->getContext()->getId()) {
                        throw new Exception('Can not request submission files from another context.');
                    }

                    $this->_submissionFiles = Repo::submissionFile()
                        ->getCollector()
                        ->filterByReviewRoundIds([(int) $reviewRound->getId()])
                        ->filterBySubmissionIds([$submissionId])
                        ->getMany()
                        ->toArray();
                } else {
                    // No review round, e.g. for dependent or query files
                    $this->_submissionFiles = [];
                }
            } else {
                $collector = Repo::submissionFile()
                    ->getCollector()
                    ->filterByFileStages([(int) $this->getData('fileStage')])
                    ->filterBySubmissionIds([(int) $this->getData('submissionId')]);
                if ($this->getAssocType() && $this->getAssocType() != Application::ASSOC_TYPE_SUBMISSION) {
                    $collector = $collector->filterByAssoc(
                        $this->getAssocType(),
                        [$this->getAssocId()]
                    );
                }

                $this->_submissionFiles = $collector->getMany()->toArray();
            }
        }

        return $this->_submissionFiles;
    }

    /**
     * Get the submission files possible to select/consider for revision by the given user.
     *
     * @param User $user
     * @param SubmissionFile $uploadedFile uploaded file
     *
     * @return array a list of SubmissionFile instances.
     */
    public function getRevisionSubmissionFilesSelection($user, $uploadedFile = null)
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $allSubmissionFiles = $this->getSubmissionFiles();
        $submissionFiles = [];
        foreach ($allSubmissionFiles as $submissionFile) {
            // The uploaded file must be excluded from the list of revisable files.
            if ($uploadedFile && $uploadedFile->getId() == $submissionFile->getId()) {
                continue;
            }
            if (
                ($submissionFile->getFileStage() == SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT || $submissionFile->getFileStage() == SubmissionFile::SUBMISSION_FILE_REVIEW_FILE) &&
                $stageAssignmentDao->getBySubmissionAndRoleIds($submissionFile->getData('submissionId'), [Role::ROLE_ID_AUTHOR], $this->getStageId(), $user->getId())
            ) {
                // Authors are not permitted to revise reviewer documents.
                continue;
            }
            $submissionFiles[] = $submissionFile;
        }
        return $submissionFiles;
    }

    //
    // Implement template methods from Form
    //
    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        // Only Genre and revised file can be set in the form. All other
        // information is generated on our side.
        $this->readUserVars(['revisedFileId']);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        // Set the workflow stage.
        $this->setData('stageId', $this->getStageId());

        // Set the review round id, if any.
        $reviewRound = $this->getReviewRound();
        if (is_a($reviewRound, 'ReviewRound')) {
            $this->setData('reviewRoundId', $reviewRound->getId());
        }

        // Retrieve the uploaded file (if any).
        $uploadedFile = $this->getData('uploadedFile');

        $user = $request->getUser();

        // Initialize the list with files available for review.
        $submissionFileOptions = [];
        $currentSubmissionFileGenres = [];

        // Go through all files and build a list of files available for review.
        $revisedFileId = $this->getRevisedFileId();
        $foundRevisedFile = false;
        $submissionFiles = $this->getRevisionSubmissionFilesSelection($user, $uploadedFile);

        foreach ((array) $submissionFiles as $submissionFile) {
            // Is this the revised file?
            if ($revisedFileId && $revisedFileId == $submissionFile->getId()) {
                // This is the revised submission file, so pass its data on to the form.
                $this->setData('genreId', $submissionFile->getGenreId());
                $foundRevisedFile = true;
            }

            // Create an entry in the list of existing files which
            // the user can select from in case he chooses to upload
            // a revision.
            $fileName = $submissionFile->getLocalizedData('name') != '' ? $submissionFile->getLocalizedData('name') : __('common.untitled');

            $submissionFileOptions[$submissionFile->getId()] = $fileName;
            $currentSubmissionFileGenres[$submissionFile->getId()] = $submissionFile->getGenreId();

            $lastSubmissionFile = $submissionFile;
        }

        // If there is only one option for a file to review, and user must revise, do not show the selector.
        if (count($submissionFileOptions) == 1 && $this->getData('revisionOnly')) {
            // There was only one option, use the last added submission file
            $this->setData('revisedFileId', $lastSubmissionFile->getId());
            $this->setData('genreId', $lastSubmissionFile->getGenreId());
        }

        // If this is not a "review only" form then add a default item.
        if (count($submissionFileOptions) && !$this->getData('revisionOnly')) {
            $submissionFileOptions = ['' => __('submission.upload.uploadNewFile')] + $submissionFileOptions;
        }

        // Make sure that the revised file (if any) really was among
        // the retrieved submission files in the current file stage.
        if ($revisedFileId && !$foundRevisedFile) {
            fatalError('Invalid revised file id!');
        }

        // Set the review file candidate data in the template.
        $this->setData('currentSubmissionFileGenres', $currentSubmissionFileGenres);
        $this->setData('submissionFileOptions', $submissionFileOptions);

        // Show ensuring an anonymous review link.
        $context = $request->getContext();
        if ($context->getData('showEnsuringLink') && in_array($this->getStageId(), [WORKFLOW_STAGE_ID_SUBMISSION, WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])) {
            $ensuringLink = new LinkAction(
                'addUser',
                new ConfirmationModal(
                    __('review.anonymousPeerReview'),
                    __('review.anonymousPeerReview.title')
                ),
                __('review.anonymousPeerReview.title')
            );

            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign('ensuringLink', $ensuringLink);
        }

        return parent::fetch($request, $template, $display);
    }
}
