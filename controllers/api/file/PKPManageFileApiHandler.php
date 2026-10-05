<?php

/**
 * @file controllers/api/file/PKPManageFileApiHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPManageFileApiHandler
 *
 * @ingroup controllers_api_file
 *
 * @brief Class defining an AJAX API for file manipulation.
 */

namespace PKP\controllers\api\file;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\controllers\wizard\fileUpload\FileUploadWizardHandler;
use PKP\controllers\wizard\fileUpload\form\SubmissionFilesMetadataForm;
use PKP\core\JSONMessage;
use PKP\notification\Notification;
use PKP\observers\events\MetadataChanged;
use PKP\security\authorization\SubmissionFileAccessPolicy;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submissionFile\SubmissionFile;

abstract class PKPManageFileApiHandler extends Handler
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR],
            ['deleteFile', 'editMetadata', 'editMetadataTab', 'saveMetadata', 'cancelFileUpload']
        );
    }

    //
    // Implement methods from PKPHandler
    //
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_MODIFY, (int) $args['submissionFileId']));

        return parent::authorize($request, $args, $roleAssignments);
    }

    //
    // Public handler methods
    //
    /**
     * Delete a file or revision
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteFile($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);
        Repo::submissionFile()->delete($submissionFile);

        $this->setupTemplate($request);
        $user = $request->getUser();
        if (!$request->getUserVar('suppressNotification')) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('notification.removedFile')]
            );
        }

        return \PKP\db\DAO::getDataChangedEvent();
    }

    /**
     * Restore original file when cancelling the upload wizard
     */
    public function cancelFileUpload(array $args, Request $request): JSONMessage
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);
        $fileIdToCancel = $request->getUserVar('fileId') ? (int)$request->getUserVar('fileId') : null;

        // The file ids of every revision, ordered newest first
        $chain = Repo::submissionFile()->getRevisions($submissionFile->getId())
            ->pluck('fileId')
            ->map(fn ($fileId): int => (int) $fileId)
            ->values();

        // Only the revision the submission file currently points at may be cancelled. It stops
        // pointing at it once another wizard run cancels an upload made on top of this one.
        if (!$fileIdToCancel
            || (int) $submissionFile->getData('fileId') !== $fileIdToCancel
            || $chain->first() !== $fileIdToCancel
        ) {
            return new JSONMessage(false, __('common.unknownError'));
        }

        $runFileIds = $request->getUserVar('uploadedFileIds');
        $runFileIds = $runFileIds === null
            ? null
            : collect((array) $runFileIds)->map(fn ($fileId): int => (int) $fileId);

        // Take back one revision at a time for as long as the step was this run's own and the state
        // the revision below it had before being replaced is still known. Anything else as a revision
        // uploaded from another user's session or from another tab, or one that was confirmed on the
        // wizard's final step - is part of the file's history and stops the walk.
        $restoreToPosition = 0;
        $originalFile = null;

        for ($position = 1; $position < $chain->count(); $position++) {
            // The upload that replaced the revision about to be taken back
            if ($runFileIds !== null && !$runFileIds->containsStrict($chain->get($position - 1))) {
                break;
            }

            $stashed = $request->getSession()->get(
                FileUploadWizardHandler::getOriginalFileSessionKey($submissionFile->getId(), $chain->get($position))
            );

            if (!is_array($stashed)
                || ($stashed['fileId'] ?? null) !== $chain->get($position)
                || empty($stashed['name'])
                || empty($stashed['uploaderUserId'])
            ) {
                break;
            }

            // The revision being cancelled may itself have been confirmed, which is exactly what
            // cancelling from the wizard's final step undoes. A confirmation further down the chain
            // was another run's and is not this one's to undo.
            if ($position > 1 && !empty($stashed['confirmed'])) {
                break;
            }

            $restoreToPosition = $position;
            $originalFile = $stashed;
        }

        // A first upload has nothing to restore. Anything else with no reachable revision would
        // leave the cancelled upload standing as the file, so refuse rather than guess.
        if ($chain->count() > 1 && !$restoreToPosition) {
            return new JSONMessage(false, __('common.unknownError'));
        }

        if ($restoreToPosition) {
            // Restore original submission file without any log as the file remain same with cancel.
            Repo::submissionFile()->edit(
                $submissionFile,
                [
                    'fileId' => $chain->get($restoreToPosition),
                    'name' => $originalFile['name'],
                    'uploaderUserId' => (int) $originalFile['uploaderUserId'],
                ],
                log: false
            );

            // The stashes the walk consumed describe state that has now been applied
            for ($position = 1; $position <= $restoreToPosition; $position++) {
                $request->getSession()->forget(
                    FileUploadWizardHandler::getOriginalFileSessionKey($submissionFile->getId(), $chain->get($position))
                );
            }
        }

        // The cancelled upload should never became part of the file's history. Deleting the files
        // cascades onto their revision rows, so what is left stays one unbroken chain.
        $abandonedFileIds = $restoreToPosition ? $chain->take($restoreToPosition) : $chain;

        foreach ($abandonedFileIds as $abandonedFileId) {
            Repo::submissionFile()->deleteRevisionLogEntries($submissionFile, $abandonedFileId);
            app()->get('file')->delete($abandonedFileId);
        }

        $this->setupTemplate($request);
        return \PKP\db\DAO::getDataChangedEvent();
    }

    /**
     * Edit submission file metadata modal.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function editMetadata($args, $request)
    {
        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);
        if ($submissionFile->getFileStage() == SubmissionFile::SUBMISSION_FILE_PROOF) {
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign('submissionFile', $submissionFile);
            $templateMgr->assign('stageId', $request->getUserVar('stageId'));
            return new JSONMessage(true, $templateMgr->fetch('controllers/api/file/editMetadata.tpl'));
        } else {
            return $this->editMetadataTab($args, $request);
        }
    }

    /**
     * Edit submission file metadata tab.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function editMetadataTab($args, $request)
    {
        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);
        $reviewRound = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ROUND);
        $stageId = $request->getUserVar('stageId');
        $form = new SubmissionFilesMetadataForm($submissionFile, $stageId, $reviewRound);
        $form->setShowButtons(true);
        return new JSONMessage(true, $form->fetch($request));
    }

    /**
     * Save the metadata of the latest revision of
     * the requested submission file.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function saveMetadata($args, $request)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);
        $reviewRound = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ROUND);
        $stageId = $request->getUserVar('stageId');
        $form = new SubmissionFilesMetadataForm($submissionFile, $stageId, $reviewRound);
        $form->readInputData();
        if ($form->validate()) {
            $form->execute();
            $submissionFile = $form->getSubmissionFile();

            // Get a list of author user IDs
            // Replaces StageAssignmentDAO::getBySubmissionAndRoleIds
            $submitterAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
                ->withRoleIds([Role::ROLE_ID_AUTHOR])
                ->get();

            $authorUserIds = $submitterAssignments
                ->pluck('user_id')
                ->all();

            // Update the notifications
            $notificationMgr = new NotificationManager(); /** @var NotificationManager $notificationMgr */
            $notificationMgr->updateNotification(
                $request,
                $this->getUpdateNotifications(),
                $authorUserIds,
                Application::ASSOC_TYPE_SUBMISSION,
                $submission->getId()
            );

            if ($reviewRound) {
                // Delete any 'revision requested' notifications since revisions are now in.
                $context = $request->getContext();

                foreach ($submitterAssignments as $submitterAssignment) {
                    Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submission->getId())
                        ->withUserId($submitterAssignment->userId)
                        ->withType(Notification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS)
                        ->withContextId($context->getId())
                        ->delete();
                }
            }

            // Inform SearchIndex of changes
            event(new MetadataChanged($submission));

            return \PKP\db\DAO::getDataChangedEvent();
        } else {
            return new JSONMessage(true, $form->fetch($request));
        }
    }

    /**
     * Get the list of notifications to be updated on metadata form submission.
     *
     * @return array
     */
    protected function getUpdateNotifications()
    {
        return [Notification::NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS];
    }
}
