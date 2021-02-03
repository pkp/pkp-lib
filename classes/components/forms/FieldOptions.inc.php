<?php
/**
 * @file classes/components/form/FieldOptions.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldOptions
 * @ingroup classes_controllers_form
 *
 * @brief A field to select from a set of checkbox or radio options.
 */
namespace PKP\components\forms;
class FieldOptions extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-options';

	/** @var string Use a checkbox or radio button input type */
	public $type = 'checkbox';

	/** @var boolean Should the user be able to re-order the options? */
	public $isOrderable = false;

	/** @var array The options which can be selected */
	public $options = [];

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['type'] = $this->type;
		$config['isOrderable'] = $this->isOrderable;
		$config['options'] = $this->options;

		return $config;
	}
}
