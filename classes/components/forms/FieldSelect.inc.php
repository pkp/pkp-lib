<?php
/**
 * @file classes/components/form/FieldSelect.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldSelect
 * @ingroup classes_controllers_form
 *
 * @brief A select field in a form.
 */
namespace PKP\components\forms;
class FieldSelect extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-select';

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
