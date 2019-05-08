<?php
/**
 * @file classes/services/interfaces/EntityWriteInterface.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EntityWriteInterface
 * @ingroup services_interfaces
 *
 * @brief An interface describing the methods a service class will implement to
 *  validate, add, edit and delete an object.
 */
namespace PKP\Services\Interfaces;

// The type of action against which data should be validated. When adding an
// entity, required properties must be present and not empty.
define('VALIDATE_ACTION_ADD', 'add');
define('VALIDATE_ACTION_EDIT', 'edit');

interface EntityWriteInterface {
	/**
	 * Validate the properties of an object
	 *
	 * Passes the properties through the SchemaService to validate them, and
	 * performs any additional checks needed to validate the entity.
	 *
	 * This does NOT authenticate the current user to perform the action.
	 *
	 * @param $action string The type of action required (add/edit). One of the
	 *  VALIDATE_ACTION_... constants.
	 * @param $props array The data to validate
	 * @param $allowedLocales array Which locales are allowed for this entity
	 * @param $primaryLocale string
	 * @return array List of error messages. The array keys are property names
	 */
	public function validate($action, $props, $allowedLocales, $primaryLocale);

	/**
	 * Add a new object
	 *
	 * This does not check if the user is authorized to add the object, or
	 * validate or sanitize this object.
	 *
	 * @param $object object
	 * @param $request Request
	 * @return object
	 */
	public function add($object, $request);

	/**
	 * Edit an object
	 *
	 * This does not check if the user is authorized to edit the object, or
	 * validate or sanitize the new object values.
	 *
	 * @param $object object
	 * @param $params Array Key/value array of new data
	 * @param $request Request
	 * @return object
	 */
	public function edit($object, $params, $request);

	/**
	 * Delete an object
	 *
	 * This does not check if the user is authorized to delete the object or if
	 * the object exists.
	 *
	 * @param $object object
	 * @return boolean
	 */
	public function delete($object);
}
