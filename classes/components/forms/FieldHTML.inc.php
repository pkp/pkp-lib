<?php
/**
 * @file classes/components/form/FieldHTML.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FieldHTML
 * @ingroup classes_controllers_form
 *
 * @brief A component for inserting HTML into a form, when you don't need any
 *  input fields or values stored.
 */
namespace PKP\components\forms;
class FieldHTML extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-html';
}
