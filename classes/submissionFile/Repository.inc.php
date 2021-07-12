<?php
/**
 * @file classes/submissionFile/Repository.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submissionFile
 *
 * @brief A repository to find and manage submission files.
 */

namespace PKP\submissionFile;

use APP\core\Application;
use APP\core\Request;
use APP\i18n\AppLocale;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\plugins\HookRegistry;
use PKP\search\SubmissionSearch;
use PKP\security\authorization\SubmissionFileAccessPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\submissionFile\maps\Schema;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var DAO $dao */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schemaa */
    public $schemaMap = Schema::class;

    /** @var Request $request */
    protected $request;

    /** @var PKPSchemaService $schemaService */
    protected $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;

        HookRegistry::register('SubmissionFile::delete::before', [$this, 'deleteSubmissionFile']);
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
    public function get(int $id): ?SubmissionFile
    {
        return $this->dao->get($id);
    }

    /** @copydoc DAO::getCount() */
    public function getCount(Collector $query): int
    {
        return $this->dao->getCount($query);
    }

    /** @copydoc DAO::getIds() */
    public function getIds(Collector $query): Collection
    {
        return $this->dao->getIds($query);
    }

    /** @copydoc DAO::getMany() */
    public function getMany(Collector $query): LazyCollection
    {
        return $this->dao->getMany($query);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
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
        AppLocale::requireComponents(
            LOCALE_COMPONENT_PKP_MANAGER,
            LOCALE_COMPONENT_APP_MANAGER
        );

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales),
            []
        );

        // Check required fields if we're adding a context
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

        $errors = [];

        if ($validator->fails()) {
            $errors = $this->schemaService
                ->formatValidationErrors(
                    $validator->errors(),
                    $this->schemaService->get($this->dao->schema),
                    $allowedLocales
                );
        }

        HookRegistry::call(
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
        $id = $this->dao->insert($submissionFile);
        HookRegistry::call('SubmissionFile::add', [$submissionFile]);

        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(
        SubmissionFile $submissionFile,
        array $params
    ): void {
        $newSubmissionFile = clone $submissionFile;
        $newSubmissionFile->setAllData(array_merge($newSubmissionFile->_data, $params));

        HookRegistry::call(
            'SubmissionFile::edit',
            [
                $newSubmissionFile,
                $submissionFile,
                $params
            ]
        );

        $this->dao->update($newSubmissionFile);
    }

    /** @copydoc DAO::delete() */
    public function delete(SubmissionFile $submissionFile): void
    {
        HookRegistry::call('SubmissionFile::delete::before', [$submissionFile]);
        $this->dao->delete($submissionFile);
        HookRegistry::call('SubmissionFile::delete', [$submissionFile]);
    }

    /**
     * Delete a collection of submission files
     */
    public function deleteMany(Collector $collector): void
    {
        $submissionFiles = $this->getMany($collector);
        foreach ($submissionFiles as $submissionFile) {
            $this->delete($submissionFile);
        }
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
     * $action it's an integer holding a flag to read or write to file stages. One of SUBMISSION_FILE_ACCESS_
     *
     * @return array List of file stages (SUBMISSION_FILE_*)
     */
    public function getAssignedFileStages(
        array $stageAssignments,
        int $action
    ): array {
        $allowedRoles = [
            Role::ROLE_ID_MANAGER,
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

        HookRegistry::call('SubmissionFile::assignedFileStages', [&$allowedFileStages, $stageAssignments, $action]);

        return $allowedFileStages;
    }

    /**
     * Get all valid file stages
     */
    public function getFileStages(): array
    {
        $stages = [
            SubmissionFile::SUBMISSION_FILE_SUBMISSION,
            SubmissionFile::SUBMISSION_FILE_NOTE,
            SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
            SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT,
            SubmissionFile::SUBMISSION_FILE_FINAL,
            SubmissionFile::SUBMISSION_FILE_COPYEDIT,
            SubmissionFile::SUBMISSION_FILE_PROOF,
            SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY,
            SubmissionFile::SUBMISSION_FILE_ATTACHMENT,
            SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
            SubmissionFile::SUBMISSION_FILE_DEPENDENT,
            SubmissionFile::SUBMISSION_FILE_QUERY,
        ];

        HookRegistry::call('SubmissionFile::fileStages', [&$stages]);

        return $stages;
    }

    /**
     * NOTE: This shouldn't be used inside a Helper file?
     *
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
     * NOTE: This shouldn't be used inside a Helper file?
     *
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

            return $this->getWorkflowStageId($parentFile);
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

            return $reviewRound->getStageId();
        }

        if ($fileStage === SubmissionFile::SUBMISSION_FILE_QUERY) {
            // This file should be associated with a note. If not, fail.
            if ($submissionFile->getData('assocType') != PKPApplication::ASSOC_TYPE_NOTE) {
                return null;
            }

            // Get the associated note.
            $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
            $note = $noteDao->getById($submissionFile->getData('assocId'));
            if (!$note) {
                return null;
            }

            // Get the associated query.
            $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
            $query = $queryDao->getById($note->getAssocId());

            // The note should be associated with a query. If not, fail.
            if ($query->getAssocType() != PKPApplication::ASSOC_TYPE_QUERY) {
                return null;
            }

            // The query will have an associated file stage.
            return $query ? $query->getStageId() : null;
        }

        throw new Exception('Could not determine the workflow stage id from submission file ' . $submissionFile->getId() . ' with file stage ' . $submissionFile->getData('fileStage'));
    }

    /**
     * NOTE: This shouldn't be used inside a Helper file?
     *
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

        HookRegistry::call('SubmissionFile::supportsDependentFiles', [&$result, $submissionFile]);

        return $result;
    }

    /**
     * Delete related objects when a submission file is deleted
     */
    public function deleteSubmissionFile(array $args): void
    {
        $submissionFile = $args[0];

        // Remove galley associations and update search index
        if ($submissionFile->getData('assocType') === PKPApplication::ASSOC_TYPE_REPRESENTATION) {
            $galleyDao = DAORegistry::getDAO('ArticleGalleyDAO'); /* @var $galleyDao ArticleGalleyDAO */
            $galley = $galleyDao->getById($submissionFile->getData('assocId'));
            if ($galley && $galley->getData('submissionFileId') == $submissionFile->getId()) {
                $galley->_data['submissionFileId'] = null; // Work around pkp/pkp-lib#5740
                $galleyDao->updateObject($galley);
            }
            // To-Do: Implement 4622 job cleaning here
            $articleSearchIndex = Application::getSubmissionSearchIndex();
            $articleSearchIndex->deleteTextIndex($submissionFile->getData('submissionId'), SubmissionSearch::SUBMISSION_SEARCH_GALLEY_FILE, $submissionFile->getId());
        }
    }
}
