<?php
/**
 * @file classes/submissionFile/Repository.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage submission files.
 */

namespace PKP\submissionFile;

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\log\SubmissionEmailLogDAO;
use PKP\log\SubmissionEmailLogEntry;
use PKP\log\event\SubmissionFileEventLogEntry;
use PKP\mail\mailables\RevisedVersionNotify;
use PKP\note\NoteDAO;
use PKP\notification\PKPNotification;
use PKP\plugins\Hook;
use PKP\query\QueryDAO;
use PKP\security\authorization\SubmissionFileAccessPolicy;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\maps\Schema;
use PKP\validation\ValidatorFactory;

abstract class Repository
{
    public DAO $dao;
    public string $schemaMap = Schema::class;
    protected Request $request;
    /** @var PKPSchemaService<SubmissionFile> */
    protected PKPSchemaService $schemaService;

    /** @var array<int> $reviewFileStages The file stages that are part of a review workflow stage */
    public array $reviewFileStages = [];

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->schemaService = $schemaService;
        $this->dao = $dao;
        $this->request = $request;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): SubmissionFile
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }

        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id, int $submissionId = null): ?SubmissionFile
    {
        return $this->dao->get($id, $submissionId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, int $submissionId = null): bool
    {
        return $this->dao->exists($id, $submissionId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * submission Files to their schema
     */
    public function getSchemaMap(): Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a submission file
     *
     * Perform validation checks on data used to add or edit a submission file.
     *
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(
        ?SubmissionFile $object,
        array $props,
        array $allowedLocales,
        string $primaryLocale
    ): array {
        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales),
            []
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($this->dao->schema), $allowedLocales);

        // Do not allow the uploaderUserId or createdAt properties to be modified
        if ($object) {
            $validator->after(function ($validator) use ($props) {
                if (
                    !empty($props['uploaderUserId']) &&
                    !$validator->errors()->get('uploaderUserId')
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'uploaderUserId',
                            __('submission.file.notAllowedUploaderUserId')
                        );
                }

                if (
                    !empty($props['createdAt']) &&
                    !$validator->errors()->get('createdAt')
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'createdAt',
                            __('api.files.400.notAllowedCreatedAt')
                        );
                }
            });
        }

        // Make sure that file stage and assocType match
        if (isset($props['assocType'])) {
            $validator->after(function ($validator) use ($props) {
                if (
                    $props['assocType'] === PKPApplication::ASSOC_TYPE_REVIEW_ROUND &&
                    !in_array(
                        $props['fileStage'],
                        [SubmissionFile::SUBMISSION_FILE_REVIEW_FILE, SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION, SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE, SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION]
                    )
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'assocType',
                            __('api.submissionFiles.400.badReviewRoundAssocType')
                        );
                }

                if ($props['assocType'] === PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT && $props['fileStage'] !== SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT) {
                    $validator
                        ->errors()
                        ->add(
                            'assocType',
                            __('api.submissionFiles.400.badReviewAssignmentAssocType')
                        );
                }

                if (
                    $props['assocType'] === PKPApplication::ASSOC_TYPE_SUBMISSION_FILE &&
                    $props['fileStage'] !== SubmissionFile::SUBMISSION_FILE_DEPENDENT
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'assocType',
                            __('api.submissionFiles.400.badDependentFileAssocType')
                        );
                }

                if (
                    $props['assocType'] === PKPApplication::ASSOC_TYPE_NOTE &&
                    $props['fileStage'] !== SubmissionFile::SUBMISSION_FILE_NOTE
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'assocType',
                            __('api.submissionFiles.400.badNoteAssocType')
                        );
                }

                if (
                    $props['assocType'] === PKPApplication::ASSOC_TYPE_REPRESENTATION &&
                    $props['fileStage'] !== SubmissionFile::SUBMISSION_FILE_PROOF
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'assocType',
                            __('api.submissionFiles.400.badRepresentationAssocType')
                        );
                }
            });
        }

        $errors = [];

        if ($validator->fails()) {
            $errors = $this->schemaService
                ->formatValidationErrors(
                    $validator->errors(),
                    $this->schemaService->get($this->dao->schema),
                    $allowedLocales
                );
        }

        Hook::call(
            'SubmissionFile::validate',
            [
                &$errors,
                $object,
                $props,
                $allowedLocales,
                $primaryLocale
            ]
        );

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(SubmissionFile $submissionFile): int
    {
        $submissionFile->setData('createdAt', Core::getCurrentDate());
        $submissionFile->setData('updatedAt', Core::getCurrentDate());

        $submissionFileId = $this->dao->insert($submissionFile);

        $submissionFile = $this->get($submissionFileId);

        Hook::call('SubmissionFile::add', [$submissionFile]);

        $logData = $this->getSubmissionFileLogData($submissionFile);

        $logEntry = Repo::eventLog()->newDataObject(array_merge(
            $logData,
            [
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION_FILE,
                'assocId' => $submissionFile->getId(),
                'eventType' => SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD,
                'dateLogged' => Core::getCurrentDate(),
                'message' => 'submission.event.fileUploaded',
                'isTranslated' => false,
            ]
        ));
        Repo::eventLog()->add($logEntry);

        $submission = Repo::submission()->get($submissionFile->getData('submissionId'));

        $logEntry = Repo::eventLog()->newDataObject(array_merge(
            $logData,
            [
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD,
                'dateLogged' => Core::getCurrentDate(),
                'message' => 'submission.event.fileRevised',
                'isTranslated' => false,
            ]
        ));
        Repo::eventLog()->add($logEntry);

        // Update status and notifications when revisions have been uploaded
        if ($submissionFile->getData('fileStage') === SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION ||
            $submissionFile->getData('fileStage') === SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION) {
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
            $reviewRound = $reviewRoundDao->getById($submissionFile->getData('assocId'));
            if (!$reviewRound) {
                throw new Exception('Submission file added to review round that does not exist.');
            }

            $reviewRoundDao->updateStatus($reviewRound);

            // Update author notifications
            $authorUserIds = [];
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
            $authorAssignments = $stageAssignmentDao->getBySubmissionAndRoleIds($submissionFile->getData('submissionId'), [Role::ROLE_ID_AUTHOR]);
            while ($assignment = $authorAssignments->next()) {
                if ($assignment->getStageId() == $reviewRound->getStageId()) {
                    $authorUserIds[] = (int) $assignment->getUserId();
                }
            }
            $notificationMgr = new NotificationManager();
            $notificationMgr->updateNotification(
                $this->request,
                [PKPNotification::NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS, PKPNotification::NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS],
                $authorUserIds,
                PKPApplication::ASSOC_TYPE_SUBMISSION,
                $submissionFile->getData('submissionId')
            );

            // Notify editors if the file is uploaded by an author
            if (in_array($submissionFile->getData('uploaderUserId'), $authorUserIds)) {
                if (!$submission) {
                    throw new Exception('Submission file added to submission that does not exist.');
                }

                $this->notifyEditorsRevisionsUploaded($submissionFile, $reviewRound);
            }
        }

        return $submissionFileId;
    }

    /** @copydoc DAO::update() */
    public function edit(
        SubmissionFile $submissionFile,
        array $params
    ): void {
        $newSubmissionFile = clone $submissionFile;
        $newSubmissionFile->setAllData(array_merge($newSubmissionFile->_data, $params));

        Hook::call(
            'SubmissionFile::edit',
            [
                $newSubmissionFile,
                $submissionFile,
                $params
            ]
        );

        $newSubmissionFile->setData('updatedAt', Core::getCurrentDate());

        $this->dao->update($newSubmissionFile);

        $newFileUploaded = !empty($params['fileId']) && $params['fileId'] !== $submissionFile->getData('fileId');

        $logData = $this->getSubmissionFileLogData($submissionFile);
        $logEntry = Repo::eventLog()->newDataObject(array_merge(
            $logData,
            [
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION_FILE,
                'assocId' => $submissionFile->getId(),
                'eventType' => $newFileUploaded ? SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD : SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_EDIT,
                'message' => $newFileUploaded ? 'submission.event.revisionUploaded' : 'submission.event.fileEdited',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
            ]
        ));
        Repo::eventLog()->add($logEntry);

        $submission = Repo::submission()->get($submissionFile->getData('submissionId'));

        Repo::eventLog()->newDataObject(array_merge(
            $logData,
            [
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => $newFileUploaded ? SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD : SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_EDIT,
                'message' => $newFileUploaded ? 'submission.event.revisionUploaded' : 'submission.event.fileEdited',
                'isTranslate' => 0,
                'dateLogged' => Core::getCurrentDate(),
            ]
        ));
    }

    /**
     * Copy a submission file to another stage
     *
     * @return int ID of the new submission file
     */
    public function copy(SubmissionFile $submissionFile, int $toFileStage, ?int $reviewRoundId = null): int
    {
        $newSubmissionFile = clone $submissionFile;
        $newSubmissionFile->setData('fileStage', $toFileStage);
        $newSubmissionFile->setData('sourceSubmissionFileId', $submissionFile->getId());
        $newSubmissionFile->setData('assocType', null);
        $newSubmissionFile->setData('assocId', null);

        if ($reviewRoundId) {
            $newSubmissionFile->setData('assocType', Application::ASSOC_TYPE_REVIEW_ROUND);
            $newSubmissionFile->setData('assocId', $reviewRoundId);
        }

        return Repo::submissionFile()->add($newSubmissionFile);
    }

    /** @copydoc DAO::delete() */
    public function delete(SubmissionFile $submissionFile): void
    {
        Hook::call('SubmissionFile::delete::before', [$submissionFile]);

        // Delete dependent files
        $this
            ->getCollector()
            ->includeDependentFiles(true)
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_DEPENDENT])
            ->filterByAssoc(Application::ASSOC_TYPE_SUBMISSION_FILE, [$submissionFile->getId()])
            ->getMany()
            ->each(function (SubmissionFile $dependentFile) {
                $this->delete($dependentFile);
            });

        // Delete notes for this submission file
        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $noteDao->deleteByAssoc(Application::ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getId());

        // Update tasks
        $notificationMgr = new NotificationManager();
        switch ($submissionFile->getData('fileStage')) {
            case SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION:
                $authorUserIds = [];
                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                $submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleIds($submissionFile->getData('submissionId'), [Role::ROLE_ID_AUTHOR]);
                while ($assignment = $submitterAssignments->next()) {
                    $authorUserIds[] = $assignment->getUserId();
                }
                $notificationMgr->updateNotification(
                    Application::get()->getRequest(),
                    [
                        Notification::NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS,
                        Notification::NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS
                    ],
                    $authorUserIds,
                    Application::ASSOC_TYPE_SUBMISSION,
                    $submissionFile->getData('submissionId')
                );
                break;

            case SubmissionFile::SUBMISSION_FILE_COPYEDIT:
                $notificationMgr->updateNotification(
                    Application::get()->getRequest(),
                    [
                        Notification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR,
                        Notification::NOTIFICATION_TYPE_AWAITING_COPYEDITS
                    ],
                    null,
                    Application::ASSOC_TYPE_SUBMISSION,
                    $submissionFile->getData('submissionId')
                );
                break;
        }

        // Get all revision file ids before they are deleted
        $revisions = $this->getRevisions($submissionFile->getId());

        // Get the review round before review round files are deleted
        if ($submissionFile->getData('fileStage') === SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION) {
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
            $reviewRound = $reviewRoundDao->getBySubmissionFileId($submissionFile->getId());
        }


        $this->dao->delete($submissionFile);

        // Delete all files that are not referenced by other submission files
        foreach ($revisions as $revision) {
            $countFileShares = $this
                ->getCollector()
                ->filterByFileIds([$revision->fileId])
                ->includeDependentFiles(true)
                ->getCount();
            if (!$countFileShares) {
                Services::get('file')->delete($revision->fileId);
            }
        }

        // Update the review round status after deletion
        if ($submissionFile->getData('fileStage') === SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION) {
            $reviewRoundDao->updateStatus($reviewRound);
        }

        // Log the deletion
        $logEntry = Repo::eventLog()->newDataObject(array_merge(
            $this->getSubmissionFileLogData($submissionFile),
            [
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION_FILE,
                'assocId' => $submissionFile->getId(),
                'eventType' => SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_DELETE,
                'message' => 'submission.event.fileDeleted',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
            ]
        ));
        Repo::eventLog()->add($logEntry);

        Hook::call('SubmissionFile::delete', [$submissionFile]);
    }

    /**
     * Get the file stage ids that a user can access based on their
     * stage assignments
     *
     * This does not return file stages for ROLE_ID_REVIEWER or ROLE_ID_READER.
     * These roles are not granted stage assignments and this method should not
     * be used for these roles.
     *
     * This method does not define access to review attachments, discussion
     * files or dependent files. Access to these files are not determined by
     * stage assignment.
     *
     * In some cases it may be necessary to apply additional restrictions. For example,
     * authors are granted write access to submission files or revisions only when other
     * conditions are met. This method only considers these an assigned file stage for
     * authors when read access is requested.
     *
     * $stageAssignments it's an array holding the stage assignments of this user.
     *   Each key is a workflow stage and value is an array of assigned roles
     * $action it's an integer holding a flag to read or write to file stages. One of SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_
     *
     * @return array List of file stages (SubmissionFile::SUBMISSION_FILE_*)
     */
    public function getAssignedFileStages(
        array $stageAssignments,
        int $action
    ): array {
        $allowedRoles = [
            Role::ROLE_ID_MANAGER,
            Role::ROLE_ID_SITE_ADMIN,
            Role::ROLE_ID_SUB_EDITOR,
            Role::ROLE_ID_ASSISTANT,
            Role::ROLE_ID_AUTHOR
        ];
        $notAuthorRoles = array_diff($allowedRoles, [Role::ROLE_ID_AUTHOR]);

        $allowedFileStages = [];

        if (
            array_key_exists(WORKFLOW_STAGE_ID_SUBMISSION, $stageAssignments) &&
            !empty(array_intersect($allowedRoles, $stageAssignments[WORKFLOW_STAGE_ID_SUBMISSION]))
        ) {
            $hasEditorialAssignment = !empty(array_intersect($notAuthorRoles, $stageAssignments[WORKFLOW_STAGE_ID_SUBMISSION]));
            // Authors only have read access
            if ($action === SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_READ || $hasEditorialAssignment) {
                $allowedFileStages[] = SubmissionFile::SUBMISSION_FILE_SUBMISSION;
            }
        }

        if (array_key_exists(WORKFLOW_STAGE_ID_INTERNAL_REVIEW, $stageAssignments)) {
            $hasEditorialAssignment = !empty(array_intersect($notAuthorRoles, $stageAssignments[WORKFLOW_STAGE_ID_INTERNAL_REVIEW]));
            // Authors can only write revision files under specific conditions
            if ($action === SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_READ || $hasEditorialAssignment) {
                $allowedFileStages[] = SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION;
            }
            // Authors can never access review files
            if ($hasEditorialAssignment) {
                $allowedFileStages[] = SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE;
            }
        }

        if (array_key_exists(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, $stageAssignments)) {
            $hasEditorialAssignment = !empty(array_intersect($notAuthorRoles, $stageAssignments[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW]));
            // Authors can only write revision files under specific conditions
            if ($action === SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_READ || $hasEditorialAssignment) {
                $allowedFileStages[] = SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION;
                $allowedFileStages[] = SubmissionFile::SUBMISSION_FILE_ATTACHMENT;
            }
            // Authors can never access review files
            if ($hasEditorialAssignment) {
                $allowedFileStages[] = SubmissionFile::SUBMISSION_FILE_REVIEW_FILE;
            }
        }

        if (
            array_key_exists(WORKFLOW_STAGE_ID_EDITING, $stageAssignments) &&
            !empty(array_intersect($allowedRoles, $stageAssignments[WORKFLOW_STAGE_ID_EDITING]))
        ) {
            $hasEditorialAssignment = !empty(array_intersect($notAuthorRoles, $stageAssignments[WORKFLOW_STAGE_ID_EDITING]));
            // Authors only have read access
            if ($action === SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_READ || $hasEditorialAssignment) {
                $allowedFileStages[] = SubmissionFile::SUBMISSION_FILE_COPYEDIT;
            }
            if ($hasEditorialAssignment) {
                $allowedFileStages[] = SubmissionFile::SUBMISSION_FILE_FINAL;
            }
        }

        if (array_key_exists(WORKFLOW_STAGE_ID_PRODUCTION, $stageAssignments) &&
            !empty(array_intersect($allowedRoles, $stageAssignments[WORKFLOW_STAGE_ID_PRODUCTION]))
        ) {
            $hasEditorialAssignment = !empty(array_intersect($notAuthorRoles, $stageAssignments[WORKFLOW_STAGE_ID_PRODUCTION]));
            // Authors only have read access
            if ($action === SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_READ || $hasEditorialAssignment) {
                $allowedFileStages[] = SubmissionFile::SUBMISSION_FILE_PROOF;
            }

            if ($hasEditorialAssignment) {
                $allowedFileStages[] = SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY;
            }
        }

        return $allowedFileStages;
    }

    /**
     * Get all valid file stages
     *
     * Valid file stages should be passed through
     * the hook SubmissionFile::fileStages.
     */
    abstract public function getFileStages(): array;

    /**
     * Get the path to a submission's file directory
     *
     * This returns the relative path from the files_dir set in the config.
     */
    public function getSubmissionDir(
        int $contextId,
        int $submissionId
    ): string {
        $dirNames = Application::getFileDirectories();
        return sprintf(
            '%s/%d/%s/%d',
            str_replace('/', '', $dirNames['context']),
            $contextId,
            str_replace('/', '', $dirNames['submission']),
            $submissionId
        );
    }

    /**
     * Get the workflow stage for a submission file
     */
    public function getWorkflowStageId(SubmissionFile $submissionFile): ?int
    {
        $fileStage = $submissionFile->getData('fileStage');

        if ($fileStage === SubmissionFile::SUBMISSION_FILE_SUBMISSION) {
            return WORKFLOW_STAGE_ID_SUBMISSION;
        }

        if (
            $fileStage === SubmissionFile::SUBMISSION_FILE_FINAL ||
            $fileStage === SubmissionFile::SUBMISSION_FILE_COPYEDIT
        ) {
            return WORKFLOW_STAGE_ID_EDITING;
        }

        if (
            $fileStage === SubmissionFile::SUBMISSION_FILE_PROOF ||
            $fileStage === SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY
        ) {
            return WORKFLOW_STAGE_ID_PRODUCTION;
        }

        if (
            $fileStage === SubmissionFile::SUBMISSION_FILE_DEPENDENT
        ) {
            $parentFile = $this->get($submissionFile->getData('assocId'));

            return $parentFile ? $this->getWorkflowStageId($parentFile) : null;
        }

        if (
            $fileStage === SubmissionFile::SUBMISSION_FILE_REVIEW_FILE ||
            $fileStage === SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE ||
            $fileStage === SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT ||
            $fileStage === SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION ||
            $fileStage === SubmissionFile::SUBMISSION_FILE_ATTACHMENT ||
            $fileStage === SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION
        ) {
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
            $reviewRound = $reviewRoundDao->getBySubmissionFileId($submissionFile->getId());

            return $reviewRound?->getStageId();
        }

        if ($fileStage === SubmissionFile::SUBMISSION_FILE_QUERY) {
            // This file should be associated with a note. If not, fail.
            if ($submissionFile->getData('assocType') != PKPApplication::ASSOC_TYPE_NOTE) {
                return null;
            }

            // Get the associated note.
            $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
            $note = $noteDao->getById($submissionFile->getData('assocId'));

            // The note should be associated with a query. If not, fail.
            if ($note?->getAssocType() != PKPApplication::ASSOC_TYPE_QUERY) {
                return null;
            }

            // Get the associated query.
            $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
            $query = $queryDao->getById($note->getAssocId());

            // The query will have an associated file stage.
            return $query ? $query->getStageId() : null;
        }

        throw new Exception('Could not determine the workflow stage id from submission file ' . $submissionFile->getId() . ' with file stage ' . $submissionFile->getData('fileStage'));
    }

    /**
     * Check if a submission file supports dependent files
     */
    public function supportsDependentFiles(SubmissionFile $submissionFile): bool
    {
        $fileStage = $submissionFile->getData('fileStage');
        $excludedFileStages = [
            SubmissionFile::SUBMISSION_FILE_DEPENDENT,
            SubmissionFile::SUBMISSION_FILE_QUERY,
        ];
        $allowedMimetypes = [
            'text/html',
            'application/xml',
            'text/xml',
        ];

        $result = !in_array($fileStage, $excludedFileStages) && in_array($submissionFile->getData('mimetype'), $allowedMimetypes);

        Hook::call('SubmissionFile::supportsDependentFiles', [&$result, $submissionFile]);

        return $result;
    }

    /**
     * Get the files for each revision of a submission file
     */
    public function getRevisions(int $submissionFileId): Collection
    {
        return DB::table('submission_file_revisions as sfr')
            ->leftJoin('files as f', 'f.file_id', '=', 'sfr.file_id')
            ->where('submission_file_id', '=', $submissionFileId)
            ->orderBy('revision_id', 'desc')
            ->select(['f.file_id as fileId', 'f.path', 'f.mimetype', 'sfr.revision_id'])
            ->get();
    }

    /**
     * Sends email to notify editors about new revision of a submission file
     */
    protected function notifyEditorsRevisionsUploaded(SubmissionFile $submissionFile): void
    {
        $submission = Repo::submission()->get($submissionFile->getData('submissionId'));
        $context = Services::get('context')->get($submission->getData('contextId'));
        $uploader = Repo::user()->get($submissionFile->getData('uploaderUserId'));
        $user = $this->request->getUser();

        // Fetch the latest notification email timestamp
        $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
        /** @var SubmissionEmailLogDAO $submissionEmailLogDao */
        $submissionEmails = $submissionEmailLogDao->getByEventType(
            $submission->getId(),
            SubmissionEmailLogEntry::SUBMISSION_EMAIL_AUTHOR_NOTIFY_REVISED_VERSION
        );
        $lastNotification = null;
        $sentDates = [];
        if ($submissionEmails) {
            while ($email = $submissionEmails->next()) {
                if ($email->getDateSent()) {
                    $sentDates[] = $email->getDateSent();
                }
            }
            if (!empty($sentDates)) {
                $lastNotification = max(array_map('strtotime', $sentDates));
            }
        }

        // Get editors assigned to the submission, consider also the recommendOnly editors
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao*/
        $reviewRound = $reviewRoundDao->getById($submissionFile->getData('assocId'));
        $editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage(
            $submission->getId(),
            $reviewRound->getStageId()
        );
        $recipients = [];
        foreach ($editorsStageAssignments as $editorsStageAssignment) {
            $editor = Repo::user()->get($editorsStageAssignment->getUserId());
            // IF no prior notification exists
            // OR if editor has logged in after the last revision upload
            // OR the last upload and notification was sent more than a day ago,
            // THEN send a new notification
            if (is_null($lastNotification) || strtotime($editor->getDateLastLogin()) > $lastNotification || strtotime('-1 day') > $lastNotification) {
                $recipients[] = $editor;
            }
        }

        if (empty($recipients)) {
            return;
        }

        $mailable = new RevisedVersionNotify($context, $submission, $uploader, $reviewRound);
        $template = Repo::emailTemplate()->getByKey($context->getId(), RevisedVersionNotify::getEmailTemplateKey());
        $mailable->body($template->getLocalizedData('body'))
            ->subject($template->getLocalizedData('subject'))
            ->sender($user)
            ->recipients($recipients)
            ->replyTo($context->getData('contactEmail'), $context->getData('contactName'));

        Mail::send($mailable);
        $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /** @var SubmissionEmailLogDAO $submissionEmailLogDao */
        $submissionEmailLogDao->logMailable(
            SubmissionEmailLogEntry::SUBMISSION_EMAIL_AUTHOR_NOTIFY_REVISED_VERSION,
            $mailable,
            $submission,
            $user
        );
    }

	/**
	 * Derive data from the submission file to record in the event log
	 */
	protected function getSubmissionFileLogData(SubmissionFile $submissionFile): array
	{
		$user = $this->request->getUser();

		return [
			'userId' => Validation::loggedInAs() ?? $user?->getId(),
			'fileStage' => $submissionFile->getData('fileStage'),
			'submissionFileId' => $submissionFile->getId(),
			'sourceSubmissionFileId' => $submissionFile->getData('sourceSubmissionFileId'),
			'fileId' => $submissionFile->getData('fileId'),
			'submissionId' => $submissionFile->getData('submissionId'),
			'filename' => $submissionFile->getData('name'),
			'username' => $user?->getUsername(),
		];
	}
}
