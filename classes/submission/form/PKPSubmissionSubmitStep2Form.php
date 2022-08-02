<?php

/**
 * @file classes/submission/form/PKPSubmissionSubmitStep2Form.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionSubmitStep2Form
 * @ingroup submission_form
 *
 * @brief Form for Step 2 of author submission: file upload
 */

namespace PKP\submission\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;

use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\file\FileManager;
use PKP\submissionFile\SubmissionFile;

class PKPSubmissionSubmitStep2Form extends SubmissionSubmitForm
{
    /**
     * Constructor.
     *
     * @param Context $context
     * @param Submission $submission
     */
    public function __construct($context, $submission)
    {
        parent::__construct($context, $submission, 2);
    }

    /**
     * @copydoc Form::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $genres = [];
        $genreResults = DAORegistry::getDAO('GenreDAO')->getEnabledByContextId($request->getContext()->getId());
        while ($genre = $genreResults->next()) {
            if ($genre->getDependent()) {
                continue;
            }
            $genres[] = $genre;
        }

        $fileUploadApiUrl = '';
        $submissionFiles = [];
        $submissionLocale = Locale::getLocale();
        if ($this->submission) {
            $fileUploadApiUrl = $request->getDispatcher()->url(
                $request,
                PKPApplication::ROUTE_API,
                $request->getContext()->getPath(),
                'submissions/' . $this->submission->getId() . '/files'
            );
            $submissionFileForm = new \PKP\components\forms\submission\PKPSubmissionFileForm($fileUploadApiUrl, $genres);

            $collector = Repo::submissionFile()
                ->getCollector()
                ->filterBySubmissionIds([$this->submission->getId()])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_SUBMISSION]);

            $submissionFiles = Repo::submissionFile()
                ->getMany($collector);

            $submissionFiles = Repo::submissionFile()
                ->getSchemaMap()
                ->summarizeMany($submissionFiles, $genres);
        }

        $templateMgr = TemplateManager::getManager($request);

        $state = [
            'components' => [
                'submissionFiles' => [
                    'addFileLabel' => __('common.addFile'),
                    'apiUrl' => $fileUploadApiUrl,
                    'cancelUploadLabel' => __('form.dropzone.dictCancelUpload'),
                    'genrePromptLabel' => __('submission.submit.genre.label'),
                    'documentTypes' => [
                        'DOCUMENT_TYPE_DEFAULT' => FileManager::DOCUMENT_TYPE_DEFAULT,
                        'DOCUMENT_TYPE_AUDIO' => FileManager::DOCUMENT_TYPE_AUDIO,
                        'DOCUMENT_TYPE_EXCEL' => FileManager::DOCUMENT_TYPE_EXCEL,
                        'DOCUMENT_TYPE_HTML' => FileManager::DOCUMENT_TYPE_HTML,
                        'DOCUMENT_TYPE_IMAGE' => FileManager::DOCUMENT_TYPE_IMAGE,
                        'DOCUMENT_TYPE_PDF' => FileManager::DOCUMENT_TYPE_PDF,
                        'DOCUMENT_TYPE_WORD' => FileManager::DOCUMENT_TYPE_WORD,
                        'DOCUMENT_TYPE_EPUB' => FileManager::DOCUMENT_TYPE_EPUB,
                        'DOCUMENT_TYPE_VIDEO' => FileManager::DOCUMENT_TYPE_VIDEO,
                        'DOCUMENT_TYPE_ZIP' => FileManager::DOCUMENT_TYPE_ZIP,
                    ],
                    'emptyLabel' => __('submission.upload.instructions'),
                    'emptyAddLabel' => __('common.upload.addFile'),
                    'fileStage' => SubmissionFile::SUBMISSION_FILE_SUBMISSION,
                    'form' => isset($submissionFileForm) ? $submissionFileForm->getConfig() : null,
                    'genres' => array_map(function ($genre) {
                        return [
                            'id' => (int) $genre->getId(),
                            'name' => $genre->getLocalizedName(),
                            'isPrimary' => !$genre->getSupplementary() && !$genre->getDependent(),
                        ];
                    }, $genres),
                    'id' => 'submissionFiles',
                    'items' => $submissionFiles->values(),
                    'options' => [
                        'maxFilesize' => Application::getIntMaxFileMBs(),
                        'timeout' => ini_get('max_execution_time') ? ini_get('max_execution_time') * 1000 : 0,
                        'dropzoneDictDefaultMessage' => __('form.dropzone.dictDefaultMessage'),
                        'dropzoneDictFallbackMessage' => __('form.dropzone.dictFallbackMessage'),
                        'dropzoneDictFallbackText' => __('form.dropzone.dictFallbackText'),
                        'dropzoneDictFileTooBig' => __('form.dropzone.dictFileTooBig'),
                        'dropzoneDictInvalidFileType' => __('form.dropzone.dictInvalidFileType'),
                        'dropzoneDictResponseError' => __('form.dropzone.dictResponseError'),
                        'dropzoneDictCancelUpload' => __('form.dropzone.dictCancelUpload'),
                        'dropzoneDictUploadCanceled' => __('form.dropzone.dictUploadCanceled'),
                        'dropzoneDictCancelUploadConfirmation' => __('form.dropzone.dictCancelUploadConfirmation'),
                        'dropzoneDictRemoveFile' => __('form.dropzone.dictRemoveFile'),
                        'dropzoneDictMaxFilesExceeded' => __('form.dropzone.dictMaxFilesExceeded'),
                    ],
                    'otherLabel' => __('about.other'),
                    'primaryLocale' => $request->getContext()->getPrimaryLocale(),
                    'removeConfirmLabel' => __('submission.submit.removeConfirm'),
                    'stageId' => WORKFLOW_STAGE_ID_SUBMISSION,
                    'title' => __('submission.files'),
                    'uploadProgressLabel' => __('submission.upload.percentComplete'),
                ],
            ],
        ];

        // Temporary workaround that allows state to be passed to a
        // page fragment retrieved by $templateMgr->fetch(). This
        // should not be done under normal circumstances!
        //
        // This should be removed when the submission wizard is updated to
        // make use of the new forms powered by Vue.js.
        $templateMgr->assign(['state' => $state]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::validate
     */
    public function validate($callHooks = true)
    {
        // Validate that all upload files have been assigned a genreId
        $collector = Repo::submissionFile()
            ->getCollector()
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_SUBMISSION])
            ->filterBySubmissionIds([$this->submission->getId()]);
        $submissionFilesIterator = Repo::submissionFile()
            ->getMany($collector);
        foreach ($submissionFilesIterator as $submissionFile) {
            if (!$submissionFile->getData('genreId')) {
                $this->addError('files', __('submission.submit.genre.error'));
                $this->addErrorField('files');
            }
        }

        return parent::validate($callHooks);
    }

    /**
     * Save changes to submission.
     *
     * @return int the submission ID
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        // Update submission
        $submission = $this->submission;

        if ($submission->getSubmissionProgress() <= $this->step) {
            $submission->stampLastActivity();
            $submission->stampModified();
            $submission->setSubmissionProgress($this->step + 1);
            Repo::submission()->dao->update($submission);
        }

        return $this->submissionId;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\form\PKPSubmissionSubmitStep2Form', '\PKPSubmissionSubmitStep2Form');
}
