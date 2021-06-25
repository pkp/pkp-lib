<?php
/**
 * @file classes/user/Repository.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class announcement
 *
 * @brief A repository to find and manage announcements.
 */

namespace PKP\user;

use APP\i18n\AppLocale;
use PKP\plugins\HookRegistry;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var DAO $dao */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schemaa */
    public $schemaMap = maps\Schema::class;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): User
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id): ?User
    {
        return $this->dao->get($id);
    }

    /** @copydoc DAO::get() */
    public function getByUsername(string $username, bool $allowDisabled = true): ?User
    {
        return $this->dao->getByUsername($username, $allowDisabled);
    }

    /**
     * Validate properties for an announcement
     *
     * Perform validation checks on data used to add or edit an announcement.
     *
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?User $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        AppLocale::requireComponents(
            LOCALE_COMPONENT_PKP_MANAGER,
            LOCALE_COMPONENT_APP_MANAGER
        );

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
            $errors = $this->schemaService->formatValidationErrors($validator->errors(), $this->schemaService->get($this->dao->schema), $allowedLocales);
        }

        HookRegistry::call('User::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(User $user): int
    {
        $id = $this->dao->insert($user);
        HookRegistry::call('User::add', [$user]);

        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(User $user, array $params)
    {
        $newUser = clone $user;
        $newUser->setAllData(array_merge($newUser->_data, $params));

        HookRegistry::call('User::edit', [$newUser, $user, $params]);

        $this->dao->update($newUser);
    }
}
