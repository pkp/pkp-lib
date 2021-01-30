<?php
/**
 * @file classes/components/form/FieldControlledVocab.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldControlledVocab
 * @ingroup classes_controllers_form
 *
 * @brief A type of autosuggest field that preloads all of its options.
 */
namespace PKP\components\forms;

use PKP\components\forms\FieldBaseAutosuggest;

class FieldAutosuggestPreset extends FieldBaseAutosuggest {
	/** @copydoc Field::$component */
	public $component = 'field-autosuggest-preset';

	/** @param array Key/value list of suggestions for this field */
	public $options = [];

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['options'] = $this->options;

		return $config;
	}
}
