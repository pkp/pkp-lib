<?php
/**
 * @file classes/doi/Repository.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class doi
 *
 * @brief A repository to find and manage DOIs.
 */

namespace PKP\doi;

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use APP\plugins\IDoiRegistrationAgency;
use APP\publication\Publication;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\LazyCollection;
use PKP\context\Context;
use PKP\context\ContextDAO;
use PKP\plugins\HookRegistry;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    public const SUFFIX_ISSUE = 'issueBased';
    public const SUFFIX_CUSTOM_PATTERN = 'customPattern';
    public const CUSTOM_SUFFIX_MANUAL = 'customId';

    public const LEGACY_CUSTOM_PUBLICATION_PATTERN = 'doiPublicationSuffixPattern';
    public const LEGACY_CUSTOM_REPRESENTATION_PATTERN = 'doiRepresentationSuffixPattern';

    public const CREATION_TIME_COPYEDIT = 'copyEditCreationTime';
    public const CREATION_TIME_PUBLICATION = 'publicationCreationTime';
    public const CREATION_TIME_NEVER = 'neverCreationTime';

    /** @var DAO $dao */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;

    /** @var Request $request */
    protected $request;

    /** @var PKPSchemaService $schemaService */
    protected $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Doi
    {
        $doi = $this->dao->newDataObject();
        if (!empty($params)) {
            $doi->setAllData($params);
        }
        return $doi;
    }

    /** @copydoc::get() */
    public function get(int $id): ?Doi
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

    /** @copydoc DAO::getCollector */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * DOIs to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Check if duplicate of this DOI has already been recorded across all contexts.
     *
     *
     */
    public function isDuplicate(Doi $doiObject, ?int $excludeDoiId = null): bool
    {
        $collector = $this->getCollector()->filterByIdentifier($doiObject->getData('doi'));
        $ids = $this->getIds($collector);

        if ($ids->count() == 0) {
            return false;
        }

        if ($excludeDoiId === null && $ids->count() > 0) {
            return true;
        }

        if ($ids->has($excludeDoiId) && $ids->count() < 2) {
            return false;
        }

        return true;
    }

    /**
     * Validate properties for a Doi
     *
     * Perform validation checks on data used to add or edit a Doi
     *
     * @param array $props A key/value array with the new data to validate
     *
     * @throws Exception
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?Doi $object, array $props): array
    {
        $errors = [];

        /** @var \PKP\validation\Illuminate\Validation\Validator $validator */
        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, []),
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            [],
            ''
        );

        // The contextId must match an existing context
        $validator->after(function ($validator) use ($object, $props) {
            if (isset($props['contextId']) && !$validator->errors()->get('contextId')) {
                if (!Services::get('context')->exists($props['contextId'])) {
                    $validator->errors()->add('contextId', __('doi.submit.noContext'));
                }
            }

            // Check for duplicates across all contexts
            $doiId = $object ? $object->getData('id') : null;
            if ($this->isDuplicate($object, $doiId)) {
                // TODO: #doi Move locale key out of pubIds plugin
                $validator->errors()->add('doi', __('plugins.pubIds.doi.editor.doiSuffixCustomIdentifierNotUnique'));
            }
        });

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors(), $this->schemaService->get(PKPSchemaService::SCHEMA_DOI), []);
        }

        HookRegistry::call('Doi::validate', [&$errors, $object, $props]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Doi $doi): int
    {
        $id = $this->dao->insert($doi);
        HookRegistry::call('Doi::add', [$doi]);

        return $id;
    }

    /** @copydoc DAO:update() */
    public function edit(Doi $doi, array $params)
    {
        $newDoi = clone $doi;
        $newDoi->setAllData(array_merge($newDoi->_data, $params));

        HookRegistry::call('Doi::edit', [$newDoi, $doi, $params]);

        $this->dao->update($newDoi);
    }

    /** @copydoc DAO::delete() */
    public function delete(Doi $doi)
    {
        HookRegistry::call('Doi::delete::before', [$doi]);
        $this->dao->delete($doi);
        HookRegistry::call('Doi::delete', [$doi]);
    }

    /**
     * Delete a collection of DOIs
     */
    public function deleteMany(Collector $collector)
    {
        $dois = $this->getMany($collector);
        foreach ($dois as $doi) {
            $this->delete($doi);
        }
    }

    /**
     * Handles updating publication/submission DOI status when metadata changes
     *
     */
    public function publicationUpdated(Publication $publication)
    {
        $doiIds = Repo::doi()->getDoisForSubmission($publication->getData('submissionId'));
        $this->dao->setDoisToStale($doiIds);
    }

    public function setDoisToStale(array $doiIds)
    {
        $this->dao->setDoisToStale($doiIds);
    }

    public function setDoisToSubmitted(array $doiIds)
    {
        $this->dao->setDoisToSubmitted($doiIds);
    }

    public function markRegistered(int $doiId)
    {
        $doi = $this->get($doiId);
        $editParams = [
            'status' => Doi::STATUS_REGISTERED,
        ];

        HookRegistry::call('Doi::markRegistered', [&$editParams]);
        $this->edit($doi, $editParams);
    }

    public function scheduleDepositAll(Context $context)
    {
        $enabledDoiTypes = $context->getData(Context::SETTING_ENABLED_DOI_TYPES);
        if (in_array(Repo::doi()::TYPE_PUBLICATION, $enabledDoiTypes) || in_array(Repo::doi()::TYPE_PUBLICATION, $enabledDoiTypes)) {
            $submissionsCollection = $this->dao->getAllDepositableSubmissionIds($context);
            $submissionData = $submissionsCollection->reduce(function ($carry, $item) {
                if ($item->submission_id) {
                    $carry['submissionIds'][] = $item->submission_id;
                }
                $carry['doiIds'][] = $item->doi_id;

                return $carry;
            }, ['submissionIds' => [], 'doiIds' => []]);

            // Schedule/queue jobs for submissions
            $contextId = $context->getId();
            $agency = $this->_getAgencyFromContext($context);

            foreach ($submissionData['submissionIds'] as $submissionId) {
                Queue::push(function () use ($submissionId, $contextId, $agency) {
                    $submission = Repo::submission()->get($submissionId);

                    /** @var ContextDAO $contextDao */
                    $contextDao = Application::getContextDAO();
                    $context = $contextDao->getById($contextId);

                    if (!$submission || !$agency) {
                        // TODO: #doi Something went wrong if there's no issue or agency. Bail out or mark failed?
                    }
                    $retResults = $agency->depositSubmissions([$submission], $context);
                });
            }

            // Mark submission DOIs as submitted
            Repo::doi()->setDoisToSubmitted($submissionData['doiIds']);
        }
    }

    /**
     * Gets all relevant DOI IDs related to a submission
     * NB: Assumes current publication only and only enabled DOI types
     *
     *
     * @return array DOI IDs
     */
    public function getDoisForSubmission(int $submissionId): array
    {
        return [];
    }

    /**
     * Helper to retrieve and confirm validity of registration agency for a given context
     *
     */
    protected function _getAgencyFromContext(Context $context): ?IDoiRegistrationAgency
    {
        $agency = $context->getConfiguredDoiAgency();
        if (empty($agency) || !($agency instanceof IDoiRegistrationAgency)) {
            return null;
        }

        return $agency;
    }

    /**
     * Generate a suffix using base32 encoding.
     * If an existing match is found, a new DOI will be generated.
     *
     *
     */
    protected function generateDefaultSuffix(int $contextId): string
    {
        // TODO: #doi Still to implement
        return '';
    }
}
