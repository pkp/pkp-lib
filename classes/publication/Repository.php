<?php
/**
 * @file classes/publication/Repository.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage publications.
 */

namespace PKP\publication;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\publication\DAO;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\observers\events\PublicationPublished;
use PKP\observers\events\PublicationUnpublished;
use PKP\orcid\OrcidManager;
use PKP\plugins\Hook;
use PKP\security\Validation;
use PKP\services\PKPSchemaService;
use PKP\submission\Genre;
use PKP\submission\PKPSubmission;
use PKP\userGroup\UserGroup;
use PKP\validation\ValidatorFactory;

abstract class Repository
{
    /** @var DAO */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;

    /** @var Request */
    protected $request;

    /** @var PKPSchemaService<Publication> */
    protected $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Publication
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $submissionId = null): bool
    {
        return $this->dao->exists($id, $submissionId);
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $submissionId = null): ?Publication
    {
        return $this->dao->get($id, $submissionId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * publications to their schema
     *
     * @param Enumerable<int,UserGroup> $userGroups
     * @param Genre[] $genres
     */
    public function getSchemaMap(Submission $submission, Enumerable $userGroups, array $genres): maps\Schema
    {
        return app('maps')->withExtensions(
            $this->schemaMap,
            [
                'submission' => $submission,
                'userGroups' => $userGroups,
                'genres' => $genres,
            ]
        );
    }

    /** @copydoc DAO:: getIdsBySetting()*/
    public function getIdsBySetting(string $settingName, $settingValue, int $contextId): Enumerable
    {
        return $this->dao->getIdsBySetting($settingName, $settingValue, $contextId);
    }

    /** @copydoc DAO:: getDateBoundaries()*/
    public function getDateBoundaries(Collector $query): object
    {
        return $this->dao->getDateBoundaries($query);
    }

    /**
     * Validate properties for a publication
     *
     * Perform validation checks on data used to add or edit a publication.
     *
     * @param Publication|null $publication The publication being edited. Pass `null` if creating a new publication
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Publication::validate [[&$errors, $publication, $props, $allowedLocales, $primaryLocale]]
     */
    public function validate(?Publication $publication, array $props, Submission $submission, Context $context): array
    {
        $primaryLocale = $submission->getData('locale');
        $allowedLocales = $submission->getpublicationLanguages($context->getSupportedSubmissionMetadataLocales());

        $errors = [];

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales),
            $this->getErrorMessageOverrides(),
        );

        ValidatorFactory::required(
            $validator,
            $publication,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            $allowedLocales,
            $primaryLocale
        );

        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($this->dao->schema), $allowedLocales);

        // The submissionId must match an existing submission
        if (isset($props['submissionId'])) {
            $validator->after(function ($validator) use ($props) {
                if (!$validator->errors()->get('submissionId')) {
                    $submission = Repo::submission()->get($props['submissionId']);
                    if (!$submission) {
                        $validator->errors()->add('submissionId', __('publication.invalidSubmission'));
                    }
                }
            });
        }


        // A title must be provided if the submission is not still in progress
        if (!$submission->getData('submissionProgress')) {
            $validator->after(function ($validator) use ($props, $publication, $primaryLocale) {
                $title = isset($props['title']) && isset($props['title'][$primaryLocale])
                    ? $props['title'][$primaryLocale]
                    : $publication?->getData('title', $primaryLocale);
                if (empty($title)) {
                    $validator->errors()->add('title.' . $primaryLocale, __('validator.required'));
                }
            });
        }

        // The urlPath must not be used in a publication attached to
        // any submission other than this publication's submission
        if (strlen($props['urlPath'] ?? '')) {
            $validator->after(function ($validator) use ($publication, $props) {
                if (!$validator->errors()->get('urlPath')) {
                    if (ctype_digit((string) $props['urlPath'])) {
                        $validator->errors()->add('urlPath', __('publication.urlPath.numberInvalid'));
                        return;
                    }

                    // If there is no submissionId the validator will throw it back anyway
                    if (is_null($publication) && !empty($props['submissionId'])) {
                        $submission = Repo::submission()->get($props['submissionId']);
                    } elseif (!is_null($publication)) {
                        $submission = Repo::submission()->get($publication->getData('submissionId'));
                    }

                    // If there's no submission we can't validate but the validator should
                    // fail anyway, so we can return without setting a separate validation
                    // error.
                    if (!$submission) {
                        return;
                    }

                    if ($this->dao->isDuplicateUrlPath($props['urlPath'], $submission->getId(), $submission->getData('contextId'))) {
                        $validator->errors()->add('urlPath', __('publication.urlPath.duplicate'));
                    }
                }
            });
        }

        // If a new file has been uploaded, check that the temporary file exists and
        // the current user owns it
        $user = Application::get()->getRequest()->getUser();
        ValidatorFactory::temporaryFilesExist(
            $validator,
            ['coverImage'],
            ['coverImage'],
            $props,
            $allowedLocales,
            $user ? $user->getId() : null
        );

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Publication::validate', [&$errors, $publication, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * Validate a publication against publishing requirements
     *
     * This validation check should return zero errors before
     * publishing a publication.
     *
     * It should not be necessary to repeat validation rules from
     * self::validate(). These rules should be applied during all add
     * or edit actions.
     *
     * This additional check should be used when a journal or press
     * wants to enforce particular publishing requirements, such as
     * requiring certain metadata or other information.
     *
     * @param array $allowedLocales The context's supported submission metadata locales
     * @param string $primaryLocale The submission's primary locale
     *
     * @hook Publication::validatePublish [[&$errors, $publication, $submission, $allowedLocales, $primaryLocale]]
     */
    public function validatePublish(Publication $publication, Submission $submission, array $allowedLocales, string $primaryLocale): array
    {
        $errors = [];

        // Don't allow declined submissions to be published
        if ($submission->getData('status') === PKPSubmission::STATUS_DECLINED) {
            $errors['declined'] = __('publication.required.declined');
        }

        // Don't allow a publication to be published before passing the review stage
        if ($submission->getData('stageId') <= WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            $errors['reviewStage'] = __('publication.required.reviewStage');
        }

        // Orcid errors
        if (OrcidManager::isEnabled()) {
            $orcidIds = [];
            foreach ($publication->getData('authors') as $author) {
                $authorOrcid = $author->getData('orcid');
                if ($authorOrcid and in_array($authorOrcid, $orcidIds)) {
                    $errors['hasDuplicateOrcids'] = __('orcid.verify.duplicateOrcidAuthor');
                } elseif ($authorOrcid && !$author->getData('orcidAccessToken')) {
                    $errors['hasUnauthenticatedOrcid'] = __('orcid.verify.hasUnauthenticatedOrcid');
                } else {
                    $orcidIds[] = $authorOrcid;
                }
            }
        }

        Hook::call('Publication::validatePublish', [&$errors, $publication, $submission, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Publication $publication): int
    {
        $publication->stampModified();
        $publicationId = $this->dao->insert($publication);
        $publication = Repo::publication()->get($publicationId);
        $submission = Repo::submission()->get($publication->getData('submissionId'));

        // Move uploaded files into place and update the settings
        if ($publication->getData('coverImage')) {
            $userId = $this->request->getUser() ? $this->request->getUser()->getId() : null;

            $submissionContext = $this->request->getContext();
            if ($submissionContext->getId() !== $submission->getData('contextId')) {
                $submissionContext = app()->get('context')->get($submission->getData('contextId'));
            }

            $supportedLocales = $submission->getPublicationLanguages($submissionContext->getSupportedSubmissionMetadataLocales());
            foreach ($supportedLocales as $localeKey) {
                if (!array_key_exists($localeKey, $publication->getData('coverImage'))) {
                    continue;
                }
                $value[$localeKey] = $this->_saveFileParam($publication, $submission, $publication->getData('coverImage', $localeKey), 'coverImage', $userId, $localeKey, true);
            }

            $this->edit($publication, ['coverImage' => $value]);
        }

        Hook::call('Publication::add', [&$publication]);

        // Update a submission's status based on the status of its publications
        Repo::submission()->updateStatus($submission);

        return $publication->getId();
    }

    /**
     * Create a new version of a publication
     *
     * Makes a copy of an existing publication, without the datePublished,
     * and makes copies of all associated objects.
     *
     * @hook Publication::version [[&$newPublication, $publication]]
     */
    public function version(Publication $publication): int
    {
        $newPublication = clone $publication;
        $newPublication->setData('id', null);
        $newPublication->setData('datePublished', null);
        $newPublication->setData('status', Submission::STATUS_QUEUED);
        $newPublication->setData('version', $publication->getData('version') + 1);
        $newPublication->stampModified();

        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if ($context->getData(Context::SETTING_DOI_VERSIONING)) {
            $newPublication->setData('doiId', null);
        }
        $newId = $this->add($newPublication);
        $newPublication = Repo::publication()->get($newId);

        $authors = $publication->getData('authors');
        if (!empty($authors)) {
            foreach ($authors as $author) {
                $newAuthor = clone $author;
                $newAuthor->setData('id', null);
                $newAuthor->setData('publicationId', $newPublication->getId());
                $newAuthorId = Repo::author()->add($newAuthor);

                if ($author->getId() === $publication->getData('primaryContactId')) {
                    $this->edit($newPublication, ['primaryContactId' => $newAuthorId]);
                }
            }
        }

        if (!empty($newPublication->getData('citationsRaw'))) {
            $citationDao = DAORegistry::getDAO('CitationDAO'); /** @var \PKP\citation\CitationDAO $citationDao */
            $citationDao->importCitations($newPublication->getId(), $newPublication->getData('citationsRaw'));
        }

        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var \PKP\submission\GenreDAO $genreDao */
        $genres = $genreDao->getEnabledByContextId($context->getId());

        $jatsFile = Repo::jats()
            ->getJatsFile($publication->getId(), null, $genres->toArray());

        if (!$jatsFile->isDefaultContent) {
            Repo::submissionFile()
                ->versionSubmissionFile($jatsFile->submissionFile, $newPublication);
        }

        $newPublication = Repo::publication()->get($newPublication->getId());

        Hook::call('Publication::version', [&$newPublication, $publication]);

        $submission = Repo::submission()->get($newPublication->getData('submissionId'));

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_CREATE_VERSION,
            'userId' => Validation::loggedInAs() ?? $request->getUser()?->getId(),
            'message' => 'publication.event.versionCreated',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($eventLog);

        return $newPublication->getId();
    }

    /** @copydoc DAO::update() */
    public function edit(Publication $publication, array $params): Publication
    {
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $userId = $this->request->getUser()?->getId();

        // Move uploaded files into place and update the params
        if (array_key_exists('coverImage', $params)) {
            $submissionContext = $this->request->getContext();
            if ($submissionContext->getId() !== $submission->getData('contextId')) {
                $submissionContext = app()->get('context')->get($submission->getData('contextId'));
            }

            $supportedLocales = $submission->getPublicationLanguages($submissionContext->getSupportedSubmissionMetadataLocales());
            foreach ($supportedLocales as $localeKey) {
                if (!array_key_exists($localeKey, $params['coverImage'])) {
                    continue;
                }
                $params['coverImage'][$localeKey] = $this->_saveFileParam($publication, $submission, $params['coverImage'][$localeKey], 'coverImage', $userId, $localeKey, true);
            }
        }

        $newPublication = Repo::publication()->newDataObject(array_merge($publication->_data, $params));
        $newPublication->stampModified();

        Hook::call('Publication::edit', [&$newPublication, $publication, $params, $this->request]);

        $this->dao->update($newPublication, $publication);

        $newPublication = Repo::publication()->get($newPublication->getId());

        $submission = Repo::submission()->get($newPublication->getData('submissionId'));

        // Log an event when publication data is updated
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE,
            'userId' => Validation::loggedInAs() ?? $userId,
            'message' => 'submission.event.general.metadataUpdated',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($eventLog);

        return $newPublication;
    }

    /**
     * Publish a publication
     *
     * This method performs all actions needed when publishing an item, such
     * as setting metadata, logging events, updating the search index, etc.
     *
     * @throws \Exception
     *
     * @see self::setStatusOnPublish()
     *
     * @hook Publication::publish::before [[&$newPublication, $publication]]
     */
    public function publish(Publication $publication)
    {
        $newPublication = clone $publication;
        $newPublication->stampModified();

        $this->setStatusOnPublish($newPublication);

        // Set the copyright and license information
        $submission = Repo::submission()->get($newPublication->getData('submissionId'));

        $itsPublished = ($newPublication->getData('status') === PKPSubmission::STATUS_PUBLISHED);

        if ($itsPublished && !$newPublication->getData('copyrightHolder')) {
            $newPublication->setData(
                'copyrightHolder',
                $submission->_getContextLicenseFieldValue(
                    null,
                    PKPSubmission::PERMISSIONS_FIELD_COPYRIGHT_HOLDER,
                    $newPublication
                )
            );
        }

        if ($itsPublished && !$newPublication->getData('copyrightYear')) {
            $newPublication->setData(
                'copyrightYear',
                $submission->_getContextLicenseFieldValue(
                    null,
                    PKPSubmission::PERMISSIONS_FIELD_COPYRIGHT_YEAR,
                    $newPublication
                )
            );
        }

        if ($itsPublished && !$newPublication->getData('licenseUrl')) {
            $newPublication->setData(
                'licenseUrl',
                $submission->_getContextLicenseFieldValue(
                    null,
                    PKPSubmission::PERMISSIONS_FIELD_LICENSE_URL,
                    $newPublication
                )
            );
        }

        Hook::call('Publication::publish::before', [&$newPublication, $publication]);

        $this->dao->update($newPublication);

        $newPublication = Repo::publication()->get($newPublication->getId());
        $submission = Repo::submission()->get($newPublication->getData('submissionId'));

        // Update a submission's status based on the status of its publications
        if ($newPublication->getData('status') !== $publication->getData('status')) {
            Repo::submission()->updateStatus($submission);
            $submission = Repo::submission()->get($submission->getId());
        }

        $msg = ($newPublication->getData('status') === Submission::STATUS_SCHEDULED) ? 'publication.event.scheduled' : 'publication.event.published';

        // Log an event when publication is published. Adjust the message depending
        // on whether this is the first publication or a subsequent version
        if (count($submission->getData('publications')) > 1) {
            $msg = ($newPublication->getData('status') === Submission::STATUS_SCHEDULED) ? 'publication.event.versionScheduled' : 'publication.event.versionPublished';
        }

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_METADATA_PUBLISH,
            'userId' => Validation::loggedInAs() ?? $this->request->getUser()?->getId(),
            'message' => $msg,
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate()
        ]);
        Repo::eventLog()->add($eventLog);

        // Mark DOIs stale (if applicable).
        if ($newPublication->getData('status') === Submission::STATUS_PUBLISHED) {
            $staleDoiIds = Repo::doi()->getDoisForSubmission($newPublication->getData('submissionId'));
            Repo::doi()->markStale($staleDoiIds);
        }

        Hook::call(
            'Publication::publish',
            [
                &$newPublication,
                $publication,
                $submission
            ]
        );

        $context = $submission->getData('contextId') === Application::get()->getRequest()->getContext()?->getId()
            ? Application::get()->getRequest()->getContext()
            : app()->get('context')->get($submission->getData('contextId'));

        event(new PublicationPublished($newPublication, $publication, $submission, $context));
    }

    /**
     * Set the status when an item is published
     *
     * Each application may handle publishing in a different way. Implement this method
     * in an app-specific child class by assigning `status` and `datePublished` for this
     * publication.
     *
     * This method should be called by self::publish().
     *
     * @hook Publication::unpublish::before [[ &$newPublication, $publication ]]
     */
    abstract protected function setStatusOnPublish(Publication $publication);

    /**
     * Unpublish a publication
     *
     * This method performs all actions needed when unpublishing an item, such
     * as changing the status, logging events, updating the search index, etc.
     *
     * @see self::setStatusOnPublish()
     *
     * @hook Publication::unpublish::before [[ &$newPublication, $publication ]]
     */
    public function unpublish(Publication $publication)
    {
        $newPublication = clone $publication;
        $newPublication->setData('status', Submission::STATUS_QUEUED);
        $newPublication->stampModified();

        Hook::call(
            'Publication::unpublish::before',
            [
                &$newPublication,
                $publication
            ]
        );

        $this->dao->update($newPublication);

        $newPublication = Repo::publication()->get($newPublication->getId());
        $submission = Repo::submission()->get($newPublication->getData('submissionId'));

        // Update a submission's status based on the status of its publications
        if ($newPublication->getData('status') !== $publication->getData('status')) {
            Repo::submission()->updateStatus($submission);
            $submission = Repo::submission()->get($submission->getId());
        }

        // Log an event when publication is unpublished. Adjust the message depending
        // on whether this is the first publication or a subsequent version
        $msg = 'publication.event.unpublished';

        if (count($submission->getData('publications')) > 1) {
            $msg = 'publication.event.versionUnpublished';
        }

        // Mark DOIs stable (if applicable).
        if ($submission->getData('status') !== Submission::STATUS_PUBLISHED) {
            $staleDoiIds = Repo::doi()->getDoisForSubmission($newPublication->getData('submissionId'));
            Repo::doi()->markStale($staleDoiIds);
        }

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UNPUBLISH,
            'userId' => Validation::loggedInAs() ?? $this->request->getUser()?->getId(),
            'message' => $msg,
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate()
        ]);
        Repo::eventLog()->add($eventLog);

        Hook::call(
            'Publication::unpublish',
            [
                &$newPublication,
                $publication,
                $submission
            ]
        );

        $context = $submission->getData('contextId') === Application::get()->getRequest()->getContext()->getId()
            ? Application::get()->getRequest()->getContext()
            : app()->get('context')->get($submission->getData('contextId'));

        event(new PublicationUnpublished($newPublication, $publication, $submission, $context));
    }

    /** @copydoc DAO::delete() */
    public function delete(Publication $publication)
    {
        Hook::call('Publication::delete::before', [&$publication]);

        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $sectionId = $publication->getData(Application::getSectionIdPropName());
        $section = $sectionId ? Repo::section()->get($sectionId) : null;

        $this->dao->delete($publication);

        // Update a submission's status based on the status of its remaining publications
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        Repo::submission()->updateStatus($submission, null, $section);

        Hook::call('Publication::delete', [&$publication]);
    }

    /**
     * Handle a publication setting for an uploaded file
     *
     * - Moves the temporary file to the public directory
     * - Resets the param value to what is expected to be stored in the db
     * - If a null value is passed, deletes any existing file
     *
     * This method is protected because all operations which edit publications should
     * go through the add and edit methods in order to ensure that
     * the appropriate hooks are fired.
     *
     * @param Publication $publication The publication being edited
     * @param Submission $submission The submission this publication is part of
     * @param mixed $value The param value to be saved. Contains the temporary
     *  file ID if a new file has been uploaded.
     * @param string $settingName The name of the setting to save, typically used
     *  in the filename.
     * @param int $userId ID of the user who owns the temporary file
     * @param string $localeKey Optional. Pass if the setting is multilingual
     * @param bool $isImage Optional. For image files which include alt text in value
     *
     * @return string|array|bool New param value or false on failure
     */
    protected function _saveFileParam(
        Publication $publication,
        Submission $submission,
        $value,
        string $settingName,
        int $userId,
        string $localeKey = '',
        bool $isImage = false
    ) {
        // If the value is null, delete any existing unused file in the system
        if (is_null($value)) {
            $oldPublication = Repo::publication()->get($publication->getId());
            $oldValue = $oldPublication->getData($settingName, $localeKey);
            $fileName = $oldValue['uploadName'] ?? null;
            if ($fileName) {
                // File may be in use by other publications
                $fileInUse = false;
                foreach ($submission->getData('publications') as $iPublication) {
                    if ($publication->getId() === $iPublication->getId()) {
                        continue;
                    }
                    $iValue = $iPublication->getData($settingName, $localeKey);
                    if (!empty($iValue['uploadName']) && $iValue['uploadName'] === $fileName) {
                        $fileInUse = true;
                        continue;
                    }
                }
                if (!$fileInUse) {
                    $publicFileManager = new PublicFileManager();
                    $publicFileManager->removeContextFile($submission->getData('contextId'), $fileName);
                }
            }
            return null;
        }

        // Check if there is something to upload
        if (empty($value['temporaryFileId'])) {
            return $value;
        }

        // Get the submission context
        $submissionContext = $this->request->getContext();
        if ($submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }

        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->getFile((int) $value['temporaryFileId'], $userId);
        $fileNameBase = join('_', ['submission', $submission->getId(), $publication->getId(), $settingName]); // eg - submission_1_1_coverImage
        $fileName = app()->get('context')->moveTemporaryFile($submissionContext, $temporaryFile, $fileNameBase, $userId, $localeKey);

        if ($fileName) {
            if ($isImage) {
                return [
                    'altText' => !empty($value['altText']) ? $value['altText'] : '',
                    'dateUploaded' => Core::getCurrentDate(),
                    'uploadName' => $fileName,
                ];
            } else {
                return [
                    'dateUploaded' => Core::getCurrentDate(),
                    'uploadName' => $fileName,
                ];
            }
        }

        return null;
    }

    /**
     * Get error message overrides for the validator
     */
    protected function getErrorMessageOverrides(): array
    {
        return [
            'locale.regex' => __('validator.localeKey'),
            'datePublished.date_format' => __('publication.datePublished.errorFormat'),
            'urlPath.regex' => __('validator.alpha_dash_period'),
        ];
    }

    /**
     * Assign categories to a publication.
     *
     * @param int[] $categoryIds
     */
    public function assignCategoriesToPublication(int $publicationId, array $categoryIds): void
    {
        $records = array_map(fn ($categoryId) => ['publication_id' => $publicationId, 'category_id' => $categoryId], $categoryIds);

        PublicationCategory::upsert($records, ['publication_id', 'category_id']);

        // delete categories that are no longer assigned
        PublicationCategory::where('publication_id', $publicationId)
            ->whereNotIn('category_id', $categoryIds)
            ->delete();
    }

    /**
     * Create all DOIs associated with the publication.
     */
    abstract protected function createDois(Publication $newPublication): void;
}
