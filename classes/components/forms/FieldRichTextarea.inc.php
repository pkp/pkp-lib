<?php
/**
 * @file classes/components/form/FieldRichTextarea.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FieldRichTextarea
 * @ingroup classes_controllers_form
 *
 * @brief A rich text editor field in a form.
 */
namespace PKP\components\forms;
class FieldRichTextarea extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-rich-textarea';

	/** @var string Optional. A preset size option. */
	public $size;

	/** @var string Optional. A preset toolbar configuration. */
	public $toolbar;

	/** @var array Optional. A list of required plugins. */
	public $plugins;

	/** @var array Optional. An assoc array of init properties to pass to TinyMCE */
	public $init;

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		if (!empty($this->size)) {
			$config['size'] = $this->size;
		}
		if (!empty($this->toolbar)) {
			$config['toolbar'] = $this->toolbar;
		}
		if (!empty($this->plugins)) {
			$config['plugins'] = $this->plugins;
		}
		if (!empty($this->init)) {
			$config['init'] = $this->init;
		}

		return $config;
	}
}
