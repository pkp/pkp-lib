<?php
/**
 * @file classes/log/event/Repository.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage event log entries.
 */

namespace PKP\log\event;

use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    public DAO $dao;

    // The name of the class to map this entity to its schema
    public string $schemaMap = maps\Schema::class;

    protected PKPRequest $request;

    protected PKPSchemaService $schemaService;

    public function __construct(DAO $dao, PKPRequest $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): EventLogEntry
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::getByKey() */
    public function get(int $key): ?EventLogEntry
    {
        return $this->dao->get($key);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * log entries to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for an event log entry
     *
     * Perform validation checks on data used to add or edit an event log entry.
     *
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?EventLogEntry $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales)
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
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('EventLog::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(EventLogEntry $logEntry, array $customPropsSchema = []): int
    {
        $id = $this->dao->insert($logEntry, $customPropsSchema);
        Hook::call('EventLog::add', [$logEntry]);

        // Stamp the submission status modification date without triggering edit event
        if ($logEntry->getData('assocType') === PKPApplication::ASSOC_TYPE_SUBMISSION) {
            $submission = Repo::submission()->get($logEntry->getData('assocId'));
            $submission->stampLastActivity();
            Repo::submission()->dao->update($submission);
        }

        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(EventLogEntry $logEntry, array $params, $customPropsSchema = [])
    {
        $newLogEntry = clone $logEntry;
        $newLogEntry->setAllData(array_merge($newLogEntry->_data, $params));

        Hook::call('EventLog::edit', [$newLogEntry, $logEntry, $params]);

        $this->dao->update($newLogEntry, $customPropsSchema);
    }

    /** @copydoc DAO::delete() */
    public function delete(EventLogEntry $logEntry)
    {
        Hook::call('EventLog::delete::before', [$logEntry]);
        $this->dao->delete($logEntry);
        Hook::call('EventLog::delete', [$logEntry]);
    }

    /**
     * Delete a collection of categories
     */
    public function deleteMany(Collector $collector)
    {
        foreach ($collector->getMany() as $logEntry) {
            /** @var EventLogEntry $logEntry */
            $this->delete($logEntry);
        }
    }
}