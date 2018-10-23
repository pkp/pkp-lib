<?php
/**
 * @file controllers/form/FieldHTML.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FieldHTML
 * @ingroup classes_controllers_form
 *
 * @brief A component for inserting HTML into a form, when you don't need any
 *  input fields or values stored.
 */
import ('lib.pkp.components.forms.Field');
class FieldHTML extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-html';
}
