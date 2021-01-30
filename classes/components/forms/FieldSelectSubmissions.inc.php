<?php
/**
 * @file classes/components/form/FieldSelectSubmissions.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldSelectSubmissions
 * @ingroup classes_controllers_form
 *
 * @brief A text field to search for and select submissions.
 */
namespace PKP\components\forms;

class FieldSelectSubmissions extends FieldBaseAutosuggest {
	/** @copydoc Field::$component */
	public $component = 'field-select-submissions';
}
