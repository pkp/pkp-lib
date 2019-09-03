<?php
/**
 * @file classes/components/form/FieldControlledVocab.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FieldControlledVocab
 * @ingroup classes_controllers_form
 *
 * @brief A type of autosuggest field for controlled vocabulary like keywords.
 */
namespace PKP\components\forms;

use PKP\components\forms\FieldAutosuggest;

class FieldControlledVocab extends FieldAutosuggest {
	/** @copydoc Field::$component */
	public $component = 'field-controlled-vocab';
}
