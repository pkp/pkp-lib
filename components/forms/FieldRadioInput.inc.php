<?php
/**
 * @file controllers/form/FieldRadioInput.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FieldRadioInput
 * @ingroup classes_controllers_form
 *
 * @brief A field to select one of a set of options, and one option is a text
 *  field for entering a custom value.
 */
import ('lib.pkp.components.forms.Field');
class FieldRadioInput extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-radio-input';

	/** @var array The options which can be selected */
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
