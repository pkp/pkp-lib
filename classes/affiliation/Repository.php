<?php

/**
 * @file classes/affiliation/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
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
use PKP\services\PKPSchemaService;
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
     * @param Author|null $author Author being edited. Pass `null` if creating a new author
     * @param array $props A key/value array with the new data to validate
     * @param Submission $submission The context's supported locales
     * @param Context $context The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Affiliation::validate [[&$errors, $object, $props, $submission, $context]]
     */
    public function validate(?Affiliation $affiliation, array $props, Submission $submission, Context $context): array
    {
        $errors = [];

        $schemaService = app()->get('schema');
        $allowedLocales = $submission->getPublicationLanguages($context->getSupportedSubmissionMetadataLocales());

        // Check if author exists
        if (isset($props['authorId'])) {
            $author = Repo::author()->get($props['authorId']);
            if (!$author) {
                $errors = $schemaService->formatValidationErrors(
                    ['affiliations-authorId', __('author.authorNotFound')]
                );
            }
        }

        if (empty($errors)) {
            $affiliation['authorId'] = $props['id'];
            $validator = ValidatorFactory::make(
                $affiliation,
                $schemaService->getValidationRules($this->dao->schema, $allowedLocales)
            );

            // FIXME @bozana consider affiliation locales for submission metadata
            // // Check for input from disallowed locales
            // ValidatorFactory::allowedLocales(
            //     $validator,
            //     $this->schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_AFFILIATION),
            //     $allowedLocales
            // );

            // The ror or one name must exist
            $validator->after(function ($validator) use ($affiliation) {
                if (empty($affiliation['ror']) && empty($affiliation['name'])) {
                    $validator->errors()->add('affiliations-affiliationId', __('author.affiliationRorAndNameEmpty'));
                }
            });

            if ($validator->fails()) {
                $errors = $this->schemaService->formatValidationErrors($validator->errors());
            }
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
     */
    public function getByAuthorId(int $authorId, ?string $submissionLocale = null): array
    {
        return $this->getCollector()
            ->filterByAuthorId($authorId)
            ->getMany($submissionLocale)
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
                if (is_a($affiliation, Affiliation::class)) {
                    $affiliationId = (int)$affiliation->getId();
                } else {
                    $affiliationId = (int)$affiliation['id'];
                }

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

            if (!is_a($affiliation, Affiliation::class)) {

                if (empty($affiliation)) continue;

                $newAffiliation = $this->newDataObject();
                $newAffiliation->setAllData($affiliation);

                $affiliation = $newAffiliation;
            }

            if (empty($affiliation->getData('authorId'))) {
                $affiliation->setData('authorId', $authorId);
            }

            if ($affiliation->getROR() !== null) {
                $affiliation->setName(null);
            }

            $this->dao->updateOrInsert($affiliation);
        }
    }

    /**
     * Migrates affiliation.
     */
    public function migrateAffiliation(string|array $userAffiliation, array $allowedLocales): ?Affiliation
    {
        $affiliation = $this->newDataObject([
            "id" => null,
            "authorId" => null,
            "ror" => null,
            "name" => null
        ]);

        if (is_string($userAffiliation)) {
            $ror = Repo::ror()->getCollector()->filterByName($userAffiliation)->getMany()->first();
            if ($ror) {
                $affiliation->setROR($ror->getROR());
            } else {
                foreach ($allowedLocales as $locale) {
                    $affiliation->setName($userAffiliation, $locale);
                }
            }
        } else {
            foreach ($userAffiliation as $locale => $name) {
                $ror = Repo::ror()->getCollector()->filterByName($name)->getMany()->first();
                if ($ror) {
                    $affiliation->setROR($ror->getROR());
                    break;
                } else {
                    $affiliation->setName($name, $locale);
                }
            }
        }

        if ($affiliation->getROR()) $affiliation->setName(null);

        return ($affiliation->getROR() || $affiliation->getName()) ? $affiliation : null;
    }
}
