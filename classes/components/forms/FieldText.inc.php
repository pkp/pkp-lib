<?php
/**
 * @file classes/components/form/FieldText.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FieldText
 * @ingroup classes_controllers_form
 *
 * @brief A basic text field in a form.
 */
namespace PKP\components\forms;
class FieldText extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-text';

	/** @var string What should the <input type=""> be? */
	public $inputType = 'text';

	/** @var string Accepts: `small`, `regular` or `large` */
	public $size;

	/** @var string A prefix to display before the input value */
	public $prefix;

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['inputType'] = $this->inputType;
		if (isset($this->size)) {
			$config['size'] = $this->size;
		}
		if (isset($this->prefix)) {
			$config['prefix'] = $this->prefix;
		}

		return $config;
	}
}
