<?php
/**
 * @file classes/components/form/FieldRichTextarea.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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

	/** @var array Optional. An assoc array of init properties to pass to TinyMCE */
	public $init;

	/** @var array Optional. A list of required plugins. */
	public $plugins = 'paste,link,noneditable';

	/** @var array Optional. A key/value list of content that can be inserted from a TinyMCE button. */
	public $preparedContent;

	/** @var boolean Whether the $preparedContent properties should be replaced in the field's initial value. */
	public $renderPreparedContent = false;

	/** @var string Optional. A preset size option. */
	public $size;

	/** @var string Optional. A preset toolbar configuration. */
	public $toolbar = 'bold italic superscript subscript | link';

	/** @var string Optional. The API endpoint to upload images to. Only include if image uploads are supported here. */
	public $uploadUrl;

	/** @var integer Optional. When a word limit is specified a word counter will be shown */
	public $wordLimit = 0;

	/**
	 * @copydoc Field::__construct()
	 */
	public function __construct($name, $args = []) {
		parent::__construct($name, $args);
	}

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		if (!empty($this->init)) {
			$config['init'] = $this->init;
		}
		$config['plugins'] = $this->plugins;
		if (!empty($this->preparedContent)) {
			$config['preparedContent'] = $this->preparedContent;
		}
		$config['renderPreparedContent'] = $this->renderPreparedContent;
		if (!empty($this->size)) {
			$config['size'] = $this->size;
		}
		$config['toolbar'] = $this->toolbar;
		if (!empty($this->uploadUrl)) {
			$config['uploadUrl'] = $this->uploadUrl;
		}
		if ($this->wordLimit) {
			$config['wordLimit'] = $this->wordLimit;
			$config['wordCountLabel'] = __('publication.wordCount');
		}

		return $config;
	}
}
