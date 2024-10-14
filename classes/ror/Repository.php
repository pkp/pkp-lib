<?php
/**
 * @file classes/ror/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage rors.
 */

namespace PKP\ror;

use APP\core\Request;
use Illuminate\Support\Facades\App;
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

    /** @var PKPSchemaService<Ror> */
    protected $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Ror
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $contextId = null): ?Ror
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * rors to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a ror
     *
     * Perform validation checks on data used to add or edit a ror.
     *
     * @param Ror|null $object Ror being edited. Pass `null` if creating a new submission
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Ror::validate [[&$errors, $object, $props, $allowedLocales, $primaryLocale]]
     */
    public function validate(?Ror $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $errors = [];

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales)
        );

        // Check required fields if we're adding a ror
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

        Hook::call('Ror::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Ror $ror): int
    {
        $id = $this->dao->insert($ror);
        Hook::call('Ror::add', [$ror]);
        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(Ror $ror, array $params): void
    {
        $newRor = clone $ror;
        $newRor->setAllData(array_merge($newRor->_data, $params));
        Hook::call('Ror::edit', [$newRor, $ror, $params]);
        $this->dao->update($newRor);
    }

    /** @copydoc DAO::delete() */
    public function delete(Ror $ror): void
    {
        Hook::call('Ror::delete::before', [$ror]);
        $this->dao->delete($ror);
        Hook::call('Ror::delete', [$ror]);
    }

    /**
     * Delete a collection of rors
     */
    public function deleteMany(Collector $collector): void
    {
        foreach ($collector->getMany() as $ror) {
            $this->delete($ror);
        }
    }

    /**
     * Insert on duplicate update.
     *
     * @param Ror $ror
     * @return void
     */
    public function updateOrInsert(Ror $ror): void
    {
        $this->dao->updateOrInsert($ror);
    }
}
