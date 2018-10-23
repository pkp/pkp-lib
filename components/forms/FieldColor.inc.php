<?php
/**
 * @file controllers/form/FieldColor.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FieldColor
 * @ingroup classes_controllers_form
 *
 * @brief A color picker field in a form.
 */
import ('lib.pkp.components.forms.Field');
class FieldColor extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-color';
}
