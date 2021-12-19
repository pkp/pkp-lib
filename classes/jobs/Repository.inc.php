<?php
/**
 * @file classes/jobs/Repository.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class jobs
 *
 * @brief A repository to find and manage jobs.
 */

namespace PKP\jobs;

use APP\core\Request;
use APP\i18n\AppLocale;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\jobs\maps\Schema;
use PKP\plugins\HookRegistry;
use PKP\services\PKPSchemaService;
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

    public function __construct(
        DAO $dao,
        Request $request,
        PKPSchemaService $schemaService
    ) {
        $this->schemaService = $schemaService;
        $this->dao = $dao;
        $this->request = $request;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Job
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }

        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id): ?Job
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
        return app()->make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * jobs to their schema
     */
    public function getSchemaMap(): Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a Job
     *
     * Perform validation checks on data used to add or edit a Job.
     *
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(
        ?Job $object,
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

        // Do not allow the property createdAt to be modified if Job is already created
        if ($object) {
            $validator->after(function ($validator) use ($props) {
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
            'Job::validate',
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
    public function add(Job $job): int
    {
        $job->setData('createdAt', Core::getCurrentDate());
        $job->setData('availableAt', Core::getCurrentDate());

        $jobId = $this->dao->insert($job);

        $job = $this->get($jobId);

        HookRegistry::call('Job::add', [$job]);

        return $job->id;
    }

    /** @copydoc DAO::delete() */
    public function delete(Job $job): void
    {
        $this->dao->delete($job);
    }

    /**
     * Delete a collection of jobs
     */
    public function deleteMany(Collector $collector): void
    {
        $jobs = $this->getMany($collector);
        foreach ($jobs as $job) {
            $this->delete($job);
        }
    }
}
