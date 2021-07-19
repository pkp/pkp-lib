<?php

/**
 * @file controllers/api/file/PKPManageFileApiHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPManageFileApiHandler
 * @ingroup controllers_api_file
 *
 * @brief Class defining an AJAX API for file manipulation.
 */

use APP\facades\Repo;
use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;

use PKP\core\JSONMessage;
use PKP\notification\PKPNotification;
use PKP\security\authorization\SubmissionFileAccessPolicy;
use PKP\security\Role;
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
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR],
            ['deleteFile', 'editMetadata', 'editMetadataTab', 'saveMetadata']
        );
        // Load submission-specific translations
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);
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
     * @param $args array
     * @param $request Request
     *
     * @return JSONMessage JSON object
     */
    public function deleteFile($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
        Repo::submissionFiles()->delete($submissionFile);

        $this->setupTemplate($request);
        $user = $request->getUser();
        if (!$request->getUserVar('suppressNotification')) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $user->getId(),
                PKPNotification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('notification.removedFile')]
            );
        }

        return \PKP\db\DAO::getDataChangedEvent();
    }

    /**
     * Edit submission file metadata modal.
     *
     * @param $args array
     * @param $request Request
     *
     * @return JSONMessage JSON object
     */
    public function editMetadata($args, $request)
    {
        $submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
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
     * @param $args array
     * @param $request Request
     *
     * @return JSONMessage JSON object
     */
    public function editMetadataTab($args, $request)
    {
        $submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
        $reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
        $stageId = $request->getUserVar('stageId');
        import('lib.pkp.controllers.wizard.fileUpload.form.SubmissionFilesMetadataForm');
        $form = new SubmissionFilesMetadataForm($submissionFile, $stageId, $reviewRound);
        $form->setShowButtons(true);
        return new JSONMessage(true, $form->fetch($request));
    }

    /**
     * Save the metadata of the latest revision of
     * the requested submission file.
     *
     * @param $args array
     * @param $request Request
     *
     * @return JSONMessage JSON object
     */
    public function saveMetadata($args, $request)
    {
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
        $reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
        $stageId = $request->getUserVar('stageId');
        import('lib.pkp.controllers.wizard.fileUpload.form.SubmissionFilesMetadataForm');
        $form = new SubmissionFilesMetadataForm($submissionFile, $stageId, $reviewRound);
        $form->readInputData();
        if ($form->validate()) {
            $form->execute();
            $submissionFile = $form->getSubmissionFile();

            // Get a list of author user IDs
            $authorUserIds = [];
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
            $submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), Role::ROLE_ID_AUTHOR);
            while ($assignment = $submitterAssignments->next()) {
                $authorUserIds[] = $assignment->getUserId();
            }

            // Update the notifications
            $notificationMgr = new NotificationManager(); /** @var NotificationManager $notificationMgr */
            $notificationMgr->updateNotification(
                $request,
                $this->getUpdateNotifications(),
                $authorUserIds,
                ASSOC_TYPE_SUBMISSION,
                $submission->getId()
            );

            if ($reviewRound) {

                // Delete any 'revision requested' notifications since revisions are now in.
                $context = $request->getContext();
                $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                $submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), Role::ROLE_ID_AUTHOR);
                while ($assignment = $submitterAssignments->next()) {
                    $notificationDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submission->getId(), $assignment->getUserId(), PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS, $context->getId());
                }
            }

            // Inform SearchIndex of changes
            $articleSearchIndex = Application::getSubmissionSearchIndex();
            $articleSearchIndex->submissionFilesChanged($submission);
            $articleSearchIndex->submissionChangesFinished();

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
        return [PKPNotification::NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS];
    }
}
