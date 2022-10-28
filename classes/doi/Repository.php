<?php

/**
 * @file classes/doi/Repository.php
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

use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use Exception;
use Illuminate\Support\Facades\App;
use PKP\context\Context;
use PKP\core\PKPString;
use PKP\Jobs\Doi\DepositSubmission;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

abstract class Repository
{
    public const TYPE_PUBLICATION = 'publication';
    public const TYPE_REPRESENTATION = 'representation';

    public const SUFFIX_DEFAULT = 'default';
    public const SUFFIX_CUSTOM_PATTERN = 'customPattern';
    public const SUFFIX_MANUAL = 'customId';

    public const CUSTOM_PUBLICATION_PATTERN = 'doiPublicationSuffixPattern';
    public const CUSTOM_REPRESENTATION_PATTERN = 'doiRepresentationSuffixPattern';

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

    /** @copydoc DAO::get() */
    public function get(int $id, int $contextId = null): ?Doi
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
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
     */
    public function isDuplicate(string $doi, ?int $excludeDoiId = null): bool
    {
        $collector = $this->getCollector()->filterByIdentifier($doi);
        $ids = $collector->getIds();

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
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?Doi $object, array $props): array
    {
        $errors = [];

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
                    $validator->errors()->add('contextId', __('api.contexts.404.contextNotFound'));
                }
            }

            // Check for duplicates across all contexts
            $doiId = $object ? $object->getData('id') : null;
            $doi = $props['doi'] ?? null;
            if ($doi !== null && $this->isDuplicate($doi, $doiId)) {
                $validator->errors()->add('doi', __('doi.editor.doiSuffixCustomIdentifierNotUnique'));
            }
        });

        $validator->after(function ($validator) use ($object, $props) {
            $doi = $props['doi'] ?? null;
            if ($doi !== null && !$validator->errors()->get('doi')) {
                $validRegexPattern = '/[^-._;()\/A-Za-z0-9]/';

                Hook::call('Doi::suffixValidation', [&$validRegexPattern]);

                $hasInvalidCharacters = PKPString::regexp_match($validRegexPattern, $doi);
                if ($hasInvalidCharacters) {
                    $validator->errors()->add('doi', __('doi.editor.doiSuffixInvalidCharacters'));
                }
            }
        });

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Doi::validate', [&$errors, $object, $props]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Doi $doi): int
    {
        $id = $this->dao->insert($doi);
        Hook::call('Doi::add', [$doi]);

        return $id;
    }

    /** @copydoc DAO:update() */
    public function edit(Doi $doi, array $params)
    {
        $newDoi = clone $doi;
        $newDoi->setAllData(array_merge($newDoi->_data, $params));

        Hook::call('Doi::edit', [$newDoi, $doi, $params]);

        $this->dao->update($newDoi);
    }

    /** @copydoc DAO::delete() */
    public function delete(Doi $doi)
    {
        Hook::call('Doi::delete::before', [$doi]);
        $this->dao->delete($doi);
        Hook::call('Doi::delete', [$doi]);
    }

    /**
     * Delete a collection of DOIs
     */
    public function deleteMany(Collector $collector)
    {
        $dois = $collector->getMany();
        foreach ($dois as $doi) {
            $this->delete($doi);
        }
    }

    /**
     * Set DOIs status to Doi::STATUS_STALE, indicating the metadata has change and needs
     * to be updated with the registration agency.
     */
    public function markStale(array $doiIds)
    {
        $this->dao->markStale($doiIds);
    }

    /**
     * Sets DOI status to Doi::STATUS_SUBMITTED, indicating the DOI has been queued to be
     * deposited with a registration agency, but the actual deposit has not yet been made.
     */
    public function markSubmitted(array $doiIds)
    {
        $this->dao->markSubmitted($doiIds);
    }

    /**
     * Manually sets DOI status to Doi::STATUS_REGISTERED. This is used in cases where the
     * DOI registration process has been complete elsewhere and needs to be recorded as
     * registered locally.
     */
    public function markRegistered(int $doiId)
    {
        $doi = $this->get($doiId);
        $editParams = [
            'status' => Doi::STATUS_REGISTERED,
        ];

        Hook::call('Doi::markRegistered', [&$editParams]);
        $this->edit($doi, $editParams);
    }

    /**
     * Manually sets DOI status to Doi::STATUS_UNREGISTERED.
     */
    public function markUnregistered(int $doiId)
    {
        $doi = $this->get($doiId);
        $editParams = [
            'status' => Doi::STATUS_UNREGISTERED,
        ];

        $this->edit($doi, $editParams);
    }

    /**
     * Schedules DOI deposits with the active registration agency for all valid and
     * unregistered/stale publication items. Items are added as a queued job to be
     * completed asynchronously.
     */
    public function depositAll(Context $context)
    {
        $enabledDoiTypes = $context->getData(Context::SETTING_ENABLED_DOI_TYPES) ?? [];
        if ($this->_checkIfSubmissionValidForDeposit($enabledDoiTypes)) {

            // If there is no configured registration agency, nothing can be deposited.
            $agency = $context->getConfiguredDoiAgency();
            if (!$agency) {
                return;
            }

            $submissionsCollection = $this->dao->getAllDepositableSubmissionIds($context);
            $submissionData = $submissionsCollection->reduce(function ($carry, $item) {
                if ($item->submission_id) {
                    $carry['submissionIds'][] = $item->submission_id;
                }
                $carry['doiIds'][] = $item->doi_id;

                return $carry;
            }, ['submissionIds' => [], 'doiIds' => []]);

            // Schedule/queue jobs for submissions
            foreach ($submissionData['submissionIds'] as $submissionId) {
                dispatch(new DepositSubmission($submissionId, $context, $agency));
            }

            // Mark submission DOIs as submitted
            Repo::doi()->markSubmitted($submissionData['doiIds']);
        }
    }

    /**
     * Creates an eight character DOI suffix
     *
     */
    protected function generateDefaultSuffix(): string
    {
        return DoiGenerator::encodeSuffix();
    }

    /**
     * Loops over valid submission DOI types to see if any are enabled
     */
    private function _checkIfSubmissionValidForDeposit(array $enabledDoiTypes): bool
    {
        foreach ($this->getValidSubmissionDoiTypes() as $validSubmissionDoiType) {
            if (in_array($validSubmissionDoiType, $enabledDoiTypes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get app-specific DOI type constants to check when scheduling deposit for submissions
     */
    abstract protected function getValidSubmissionDoiTypes(): array;

    /**
     * Gets all relevant DOI IDs related to a submission
     * NB: Assumes current publication only and only enabled DOI types
     *
     * @return array DOI IDs
     */
    abstract public function getDoisForSubmission(int $submissionId): array;

    /**
     * Compose final DOI and save to database
     *
     * @throws Exception
     */
    protected function mintAndStoreDoi(Context $context, string $doiSuffix): int
    {
        $doiPrefix = $context->getData(Context::SETTING_DOI_PREFIX);
        if (empty($doiPrefix)) {
            throw new Exception('Tried to create a DOI, but a DOI prefix is required for the context to create one.');
        }

        $completedDoi = $doiPrefix . '/' . $doiSuffix;

        $doiDataParams = [
            'doi' => $completedDoi,
            'contextId' => $context->getId()
        ];

        $doi = $this->newDataObject($doiDataParams);
        return $this->add($doi);
    }
}
