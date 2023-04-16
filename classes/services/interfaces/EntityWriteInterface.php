<?php
/**
 * @file classes/services/interfaces/EntityWriteInterface.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EntityWriteInterface
 *
 * @ingroup services_interfaces
 *
 * @brief An interface describing the methods a service class will implement to
 *  validate, add, edit and delete an object.
 */

namespace PKP\services\interfaces;

interface EntityWriteInterface
{
    // The type of action against which data should be validated. When adding an
    // entity, required properties must be present and not empty.
    public const VALIDATE_ACTION_ADD = 'add';
    public const VALIDATE_ACTION_EDIT = 'edit';

    /**
     * Validate the properties of an object
     *
     * Passes the properties through the SchemaService to validate them, and
     * performs any additional checks needed to validate the entity.
     *
     * This does NOT authenticate the current user to perform the action.
     *
     * @param string $action The type of action required (add/edit). One of the
     *  VALIDATE_ACTION_... constants.
     * @param array $props The data to validate
     * @param array $allowedLocales Which locales are allowed for this entity
     * @param string $primaryLocale
     *
     * @return array List of error messages. The array keys are property names
     */
    public function validate($action, $props, $allowedLocales, $primaryLocale);

    /**
     * Add a new object
     *
     * This does not check if the user is authorized to add the object, or
     * validate or sanitize this object.
     *
     * @param object $object
     * @param Request $request
     *
     * @return object
     */
    public function add($object, $request);

    /**
     * Edit an object
     *
     * This does not check if the user is authorized to edit the object, or
     * validate or sanitize the new object values.
     *
     * @param object $object
     * @param array $params Key/value array of new data
     * @param Request $request
     *
     * @return object
     */
    public function edit($object, $params, $request);

    /**
     * Delete an object
     *
     * This does not check if the user is authorized to delete the object or if
     * the object exists.
     *
     * @param object $object
     *
     * @return bool
     */
    public function delete($object);
}

if (!PKP_STRICT_MODE) {
    define('VALIDATE_ACTION_ADD', EntityWriteInterface::VALIDATE_ACTION_ADD);
    define('VALIDATE_ACTION_EDIT', EntityWriteInterface::VALIDATE_ACTION_EDIT);
}
