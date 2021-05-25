<?php

/**
 * @file classes/submission/form/PKPSubmissionSubmitStep2Form.inc.php
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

import('lib.pkp.classes.submission.form.SubmissionSubmitForm');

class PKPSubmissionSubmitStep2Form extends SubmissionSubmitForm {
	/**
	 * Constructor.
	 * @param $context Context
	 * @param $submission Submission
	 */
	function __construct($context, $submission) {
		parent::__construct($context, $submission, 2);
	}

	/**
	 * @copydoc Form::fetch
	 */
	function fetch($request, $template = null, $display = false) {

		// SUBMISSION_FILE_ constants
		import('lib.pkp.classes.submission.SubmissionFile');

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
		if ($this->submission) {
			$fileUploadApiUrl = $request->getDispatcher()->url(
				$request,
				ROUTE_API,
				$request->getContext()->getPath(),
				'submissions/' . $this->submission->getId() . '/files'
			);
			$submissionFileForm = new \PKP\components\forms\submission\PKPSubmissionFileForm($fileUploadApiUrl, $genres);

			$submissionFilesIterator = Services::get('submissionFile')->getMany([
				'fileStages' => [SUBMISSION_FILE_SUBMISSION],
				'submissionIds' => [$this->submission->getId()],
			]);
			foreach ($submissionFilesIterator as $submissionFile) {
				$submissionFiles[] = Services::get('submissionFile')->getSummaryProperties($submissionFile, [
					'request' => $request,
					'submission' => $this->submission,
				]);
			}
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
						'DOCUMENT_TYPE_DEFAULT' => DOCUMENT_TYPE_DEFAULT,
						'DOCUMENT_TYPE_AUDIO' => DOCUMENT_TYPE_AUDIO,
						'DOCUMENT_TYPE_EXCEL' => DOCUMENT_TYPE_EXCEL,
						'DOCUMENT_TYPE_HTML' => DOCUMENT_TYPE_HTML,
						'DOCUMENT_TYPE_IMAGE' => DOCUMENT_TYPE_IMAGE,
						'DOCUMENT_TYPE_PDF' => DOCUMENT_TYPE_PDF,
						'DOCUMENT_TYPE_WORD' => DOCUMENT_TYPE_WORD,
						'DOCUMENT_TYPE_EPUB' => DOCUMENT_TYPE_EPUB,
						'DOCUMENT_TYPE_VIDEO' => DOCUMENT_TYPE_VIDEO,
						'DOCUMENT_TYPE_ZIP' => DOCUMENT_TYPE_ZIP,
					],
					'emptyLabel' => __('submission.upload.instructions'),
					'emptyAddLabel' => __('common.upload.addFile'),
					'fileStage' => SUBMISSION_FILE_SUBMISSION,
					'form' => isset($submissionFileForm) ? $submissionFileForm->getConfig() : null,
					'genres' => array_map(function($genre) {
						return [
							'id' => (int) $genre->getId(),
							'name' => $genre->getLocalizedName(),
							'isPrimary' => !$genre->getSupplementary() && !$genre->getDependent(),
						];
					}, $genres),
					'id' => 'submissionFiles',
					'items' => $submissionFiles,
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
	function validate($callHooks = true) {

		// SUBMISSION_FILE_ constants
		import('lib.pkp.classes.submission.SubmissionFile');

		// Validate that all upload files have been assigned a genreId
		$submissionFilesIterator = Services::get('submissionFile')->getMany([
			'fileStages' => [SUBMISSION_FILE_SUBMISSION],
			'submissionIds' => [$this->submission->getId()],
		]);
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
	 * @return int the submission ID
	 */
	function execute(...$functionArgs) {
		parent::execute(...$functionArgs);

		// Update submission
		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$submission = $this->submission;

		if ($submission->getSubmissionProgress() <= $this->step) {
			$submission->stampLastActivity();
			$submission->stampModified();
			$submission->setSubmissionProgress($this->step + 1);
			$submissionDao->updateObject($submission);
		}

		return $this->submissionId;
	}
}


