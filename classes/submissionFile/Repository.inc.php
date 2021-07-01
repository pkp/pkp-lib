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

use APP\core\Request;
use APP\i18n\AppLocale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\plugins\HookRegistry;
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
}
