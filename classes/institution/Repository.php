<?php
/**
 * @file classes/institution/Repository.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class institution
 *
 * @brief A repository to find and manage institutions.
 */

namespace PKP\institution;

use APP\core\Request;
use APP\core\Services;
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\core\PKPString;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    public DAO $dao;
    public string $schemaMap = maps\Schema::class;
    protected Request $request;
    protected PKPSchemaService $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Institution
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    /** @copydoc DAO::get() */
    public function get(int $id, int $contextId = null): ?Institution
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::getSoftDeleted() */
    public function getSoftDeleted(Collector $query): LazyCollection
    {
        return $this->dao->getSoftDeleted($query);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * institutions to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for an institution
     *
     * Perform validation checks on data used to add or edit an institution.
     *
     * @param Institution|null $object Institution being edited. Pass `null` if creating a new submission
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?Institution $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $errors = [];

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales)
        );

        // Check required fields if we're adding an institution
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

        // The contextId must match an existing context
        $validator->after(function ($validator) use ($props) {
            if (isset($props['contextId']) && !$validator->errors()->get('contextId')) {
                $institutionContext = Services::get('context')->get($props['contextId']);
                if (!$institutionContext) {
                    $validator->errors()->add('contextId', __('manager.institutions.noContext'));
                }
            }
            if (!empty($props['ipRanges']) && !$validator->errors()->get('ipRanges')) {
                foreach ($props['ipRanges'] as $ipRange) {
                    if (!PKPString::regexp_match(
                        '/^' .
                        // IP4 address (with or w/o wildcards) or IP4 address range (with or w/o wildcards) or CIDR IP4 address
                        '((([0-9]|[1-9][0-9]|[1][0-9]{2}|[2][0-4][0-9]|[2][5][0-5]|[' . Institution::IP_RANGE_WILDCARD . '])([.]([0-9]|[1-9][0-9]|[1][0-9]{2}|[2][0-4][0-9]|[2][5][0-5]|[' . Institution::IP_RANGE_WILDCARD . '])){3}((\s)*[' . Institution::IP_RANGE_RANGE . '](\s)*([0-9]|[1-9][0-9]|[1][0-9]{2}|[2][0-4][0-9]|[2][5][0-5]|[' . Institution::IP_RANGE_WILDCARD . '])([.]([0-9]|[1-9][0-9]|[1][0-9]{2}|[2][0-4][0-9]|[2][5][0-5]|[' . Institution::IP_RANGE_WILDCARD . '])){3}){0,1})|(([0-9]|[1-9][0-9]|[1][0-9]{2}|[2][0-4][0-9]|[2][5][0-5])([.]([0-9]|[1-9][0-9]|[1][0-9]{2}|[2][0-4][0-9]|[2][5][0-5])){3}([\/](([3][0-2]{0,1})|([1-2]{0,1}[0-9])))))' .
                        '$/i',
                        trim($ipRange)
                    )) {
                        $validator->errors()->add('ipRanges', __('manager.institutions.invalidIPRange'));
                        break;
                    }
                }
            }
        });

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors(), $this->schemaService->get($this->dao->schema), $allowedLocales);
        }

        Hook::call('Institution::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Institution $institution): int
    {
        $id = $this->dao->insert($institution);
        Hook::call('Institution::add', [$institution]);
        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(Institution $institution, array $params): void
    {
        $newInstitution = clone $institution;
        $newInstitution->setAllData(array_merge($newInstitution->_data, $params));
        Hook::call('Institution::edit', [$newInstitution, $institution, $params]);
        $this->dao->update($newInstitution);
    }

    /** @copydoc DAO::delete() */
    public function delete(Institution $institution): void
    {
        Hook::call('Institution::delete::before', [$institution]);
        $this->dao->delete($institution);
        Hook::call('Institution::delete', [$institution]);
    }

    /**
     * Delete a collection of institutions
     */
    public function deleteMany(Collector $collector): void
    {
        foreach ($collector->getMany() as $institution) {
            $this->delete($institution);
        }
    }
}
