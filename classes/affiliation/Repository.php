<?php
/**
 * @file classes/affiliation/Repository.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage affiliations.
 */

namespace PKP\affiliation;

use APP\core\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var DAO */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;

    /** @var Request */
    protected $request;

    /** @var PKPSchemaService<Affiliation> */
    protected $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /**
     * @copydoc DAO::newDataObject()
     */
    public function newDataObject(array $params = []): Affiliation
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /**
     * @copydoc DAO::exists()
     */
    public function exists(int $id, ?int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    /**
     * @copydoc DAO::get()
     */
    public function get(int $id, ?int $contextId = null): ?Affiliation
    {
        return $this->dao->get($id, $contextId);
    }

    /**
     * @copydoc DAO::getCollector()
     */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * affiliations to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a affiliation
     *
     * Perform validation checks on data used to add or edit a affiliation.
     *
     * @param Affiliation|null $object Affiliation being edited. Pass `null` if creating a new submission
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Affiliation::validate [[&$errors, $object, $props, $allowedLocales, $primaryLocale]]
     */
    public function validate(?Affiliation $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $errors = [];

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales)
        );

        // Check required fields if we're adding a affiliation
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

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Affiliation::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * @copydoc DAO::insert()
     */
    public function add(Affiliation $row): int
    {
        $id = $this->dao->insert($row);
        Hook::call('Affiliation::add', [$row]);
        return $id;
    }

    /**
     * @copydoc DAO::update()
     */
    public function edit(Affiliation $row, array $params): void
    {
        $newRow = clone $row;
        $newRow->setAllData(array_merge($newRow->_data, $params));
        Hook::call('Affiliation::edit', [$newRow, $row, $params]);
        $this->dao->update($newRow);
    }

    /**
     * @copydoc DAO::delete()
     */
    public function delete(Affiliation $row): void
    {
        Hook::call('Affiliation::delete::before', [$row]);
        $this->dao->delete($row);
        Hook::call('Affiliation::delete', [$row]);
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
     * @param int $authorId
     *
     * @return LazyCollection
     */
    public function getAffiliations(int $authorId): LazyCollection
    {
        return $this->getCollector()
            ->filterByAuthorIds([$authorId])
            ->getMany();
    }

    /**
     * Save affiliations.
     *
     * @param array|Affiliation[] $affiliations
     *
     * @return void
     */
    public function saveAffiliations(array $affiliations): void
    {
        foreach ($affiliations as $affiliation) {

            if (!($affiliation instanceof Affiliation)) {

                if (empty($affiliation['_data'])) continue;

                $newAffiliation = $this->newDataObject();
                $newAffiliation->setAllData($affiliation['_data']);

                $affiliation = $newAffiliation;
            }

            $this->dao->updateOrInsert($affiliation);
        }
    }

    /**
     * Delete an author's affiliations
     *
     * @param int $authorId
     *
     * @return void
     */
    public function deleteByAuthorId(int $authorId): void
    {
        $affiliations = $this->getCollector()
            ->filterByAuthorIds([$authorId])
            ->getMany();

        foreach ($affiliations as $affiliation) {
            $this->dao->delete($affiliation);
        }
    }
}
