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

        // Revisions are ordered newest first
        $revisions = Repo::submissionFile()->getRevisions($submissionFile->getId())->values();

        // Only the revision the submission file currently points at may be cancelled
        if (!$fileIdToCancel
            || (int) $submissionFile->getData('fileId') !== $fileIdToCancel
            || (int) $revisions->first()?->fileId !== $fileIdToCancel
        ) {
            return new JSONMessage(false);
        }

        // A previous revision exists only when the cancelled upload replaced an existing file;
        // a first upload has nothing to restore.
        if ($previousRevision = $revisions->get(1)) {
            // Recorded by FileUploadWizardHandler::uploadFile() before the revision replaced the
            // original file. It is only usable if it describes the revision the chain is about to
            // fall back to; anything else is left over from an abandoned wizard run.
            $originalFile = $request->getSession()->remove(
                FileUploadWizardHandler::getOriginalFileSessionKey($submissionFile->getId())
            );

            if (!is_array($originalFile)
                || ($originalFile['fileId'] ?? null) !== (int) $previousRevision->fileId
                || empty($originalFile['name'])
                || empty($originalFile['uploaderUserId'])
            ) {
                return new JSONMessage(false);
            }

            // Restore original submission file
            Repo::submissionFile()->edit(
                $submissionFile,
                [
                    'fileId' => (int) $previousRevision->fileId,
                    'name' => $originalFile['name'],
                    'uploaderUserId' => (int) $originalFile['uploaderUserId'],
                ]
            );
        }

        // Remove uploaded file
        app()->get('file')->delete($fileIdToCancel);

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
