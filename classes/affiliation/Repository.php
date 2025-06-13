<?php

/**
 * @file classes/affiliation/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage affiliations.
 */

namespace PKP\affiliation;

use APP\author\Author;
use APP\core\Request;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\App;
use PKP\context\Context;
use PKP\plugins\Hook;
use PKP\ror\Ror;
use PKP\services\PKPSchemaService;
use PKP\user\User;
use PKP\validation\ValidatorFactory;

class Repository
{
    public DAO $dao;

    /** The name of the class to map this entity to its schema */
    public string $schemaMap = maps\Schema::class;

    protected Request $request;

    /** @var PKPSchemaService<Affiliation> */
    protected PKPSchemaService $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Affiliation
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $authorId = null): bool
    {
        return $this->dao->exists($id, $authorId);
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $authorId = null): ?Affiliation
    {
        return $this->dao->get($id, $authorId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping affiliations to their schema.
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for an affiliation
     *
     * Perform validation checks on data used to add or edit an affiliation.
     *
     * @param Affiliation|null $affiliation Affiliation being edited. Pass `null` if creating a new affiliation
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Affiliation::validate [[$errors, $affiliation, $props, $submission, $context]]
     */
    public function validate(?Affiliation $affiliation, array $props, Submission $submission, Context $context): array
    {
        $errors = [];

        $schemaService = app()->get('schema');
        $allowedLocales = $submission->getPublicationLanguages($context->getSupportedSubmissionMetadataLocales());
        $primaryLocale = $submission->getData('locale');

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules($this->dao->schema, $allowedLocales)
        );


        // Check required fields if we're adding an institution
        ValidatorFactory::required(
            $validator,
            $affiliation,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            $allowedLocales,
            $primaryLocale
        );

        ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_AFFILIATION), $allowedLocales);

        // The author needs to exist, as well as ror or one name
        $validator->after(function ($validator) use ($props, $primaryLocale) {
            if (isset($props['authorId']) && !$validator->errors()->get('authorId')) {
                $author = Repo::author()->get($props['authorId']);
                if (!$author) {
                    $validator->errors()->add('authorId', __('author.authorNotFound'));
                }
            }
            if (empty($props['ror'])) {
                if (empty($props['name'])) {
                    $validator->errors()->add('name', __('author.affiliationRorAndNameEmpty'));
                } else {
                    // a name must exist in submission locale
                    if (!array_key_exists($primaryLocale, $props['name']) || empty($props['name'][$primaryLocale])) {
                        $validator->errors()->add("name.{$primaryLocale}", __('author.affiliationNamePrimaryLocaleMissing'));
                    }
                }
            }
        });

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Affiliation::validate', [&$errors, $affiliation, $props, $submission, $context]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Affiliation $affiliation): int
    {
        $id = $this->dao->insert($affiliation);
        Hook::call('Affiliation::add', [$affiliation]);
        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(Affiliation $affiliation, array $params): void
    {
        $newRow = clone $affiliation;
        $newRow->setAllData(array_merge($newRow->_data, $params));
        Hook::call('Affiliation::edit', [$newRow, $affiliation, $params]);
        $this->dao->update($newRow);
    }

    /** @copydoc DAO::delete() */
    public function delete(Affiliation $affiliation): void
    {
        Hook::call('Affiliation::delete::before', [$affiliation]);
        $this->dao->delete($affiliation);
        Hook::call('Affiliation::delete', [$affiliation]);
    }

    /**
     * Delete a collection of affiliations
     */
    public function deleteMany(Collector $collector): void
    {
        foreach ($collector->getMany() as $row) {
            $this->delete($row);
        }
    }

    /**
     * Get all affiliations for a given author.
     *
     * @return array<Affiliation>
     */
    public function getByAuthorId(int $authorId): array
    {
        return $this->getCollector()
            ->filterByAuthorId($authorId)
            ->getMany()
            ->all();
    }

    /**
     * Save affiliations.
     */
    public function saveAffiliations(Author $author): void
    {
        $affiliations = $author->getAffiliations();
        $authorId = $author->getId();

        // delete all affiliations if parameter $affiliations empty
        if (empty($affiliations)) {
            $this->dao->deleteByAuthorId($authorId);
            return;
        }

        // deleted affiliations not in param $affiliations
        // do this before insert/update, otherwise inserted will be deleted
        $currentAffiliations = $this->getByAuthorId($authorId);
        foreach ($currentAffiliations as $currentAffiliation) {
            $rowFound = false;
            $currentAffiliationId = $currentAffiliation->getId();

            foreach ($affiliations as $affiliation) {
                // a new affiliation will not have the ID,
                // so it will not match any currentAffiliationId
                $affiliationId = (int) $affiliation->getId();
                if ($currentAffiliationId === $affiliationId) {
                    $rowFound = true;
                    break;
                }
            }

            if (!$rowFound) {
                $this->dao->delete($currentAffiliation);
            }
        }

        // insert, update
        foreach ($affiliations as $affiliation) {
            if (empty($affiliation->getData('authorId'))) {
                $affiliation->setData('authorId', $authorId);
            }
            if ($affiliation->getRor() !== null) {
                $affiliation->setName(null);
            }
            $this->dao->updateOrInsert($affiliation);
        }
    }

    /**
     * Migrates user affiliation.
     */
    public function migrateUserAffiliation(User $user, Submission $submission, Context $context): ?Affiliation
    {
        $allowedLocales = $submission->getPublicationLanguages($context->getSupportedSubmissionMetadataLocales());
        $submissionLocale = $submission->getData('locale');

        $affiliation = $this->newDataObject();
        $userAffiliations = $user->getAffiliation(null);
        if (empty($userAffiliations) || count(array_filter($userAffiliations)) == 0) {
            return null;
        }
        foreach ($userAffiliations as $locale => $name) {
            $ror = Repo::ror()->getCollector()->filterByName($name)->getMany()->first();
            if ($ror) {
                $affiliation->setRor($ror->getRor());
                break;
            } else {
                if (in_array($locale, $allowedLocales)) {
                    $affiliation->setName($name, $locale);
                }
            }
        }
        if ($affiliation->getRor()) {
            $affiliation->setName(null);
        } else {
            if (!array_key_exists($submissionLocale, $affiliation->getName())) {
                $affiliation->setName($user->getData('name', $user->getDefaultLocale()), $submissionLocale);
            }
        }
        return ($affiliation->getRor() || $affiliation->getName()) ? $affiliation : null;
    }
}
