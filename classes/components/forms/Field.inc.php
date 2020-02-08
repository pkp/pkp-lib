<?php
/**
 * @file classes/components/form/Field.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Field
 * @ingroup classes_controllers_form
 *
 * @brief A base class representing a single field in a form.
 */
namespace PKP\components\forms;

abstract class Field {
	/** @var string Which UI Library component this field represents */
	public $component;

	/** @var string The form input name for this field */
	public $name;

	/** @var string|object Field label or multilingual object matching locales to labels, eg  ['en_US' => 'Label', 'fr_CA' => 'Étiquette'] */
	public $label = '';

	/** @var string Field description */
	public $description;

	/** @var string Field tooltip */
	public $tooltip;

	/** @var string Field help topic. Refers to the /dev/docs file name without .md */
	public $helpTopic;

	/** @var string Field help section. An optional anchor link to open to when loading the helpTopic. */
	public $helpSection;

	/** @var string Which group should this field be placed in? */
	public $groupId;

	/** @var boolean Is this field required? */
	public $isRequired = false;

	/** @var boolean Is this field multilingual? */
	public $isMultilingual = false;

	/** @var mixed The value of this field. If multilingual, expects a key/value array: ['en_US', => 'English value', 'fr_CA' => 'French value'] */
	public $value;

	/** @var mixed A default for this field when no value is specified. */
	public $default;

	/** @var array Key/value translations required for this field. Array will be merged with all i18n keys in the form. */
	public $i18n = [];

	/**
	 * Only show this field when the field named here is not empty. Match an exact
	 * value by passing an array:
	 *
	 * $this->showWhen = ['fieldName', 'expectedValue'];
	 *
	 * @var string|array
	 */
	public $showWhen;

	/** @var array List of required properties for this field. */
	private $_requiredProperties = array('name', 'component');

	/**
	 * Initialize the form field
	 *
	 * @param $name string
	 * @param $args array [
	 *  @option label string|object
	 *  @option groupId string
	 *  @option isRequired boolean
	 *  @option isMultilingual boolean
	 * ]
	 */
	public function __construct($name, $args = array()) {
		$this->name = $name;
		foreach ($args as $key => $value) {
			if (property_exists($this, $key)) {
				$this->{$key} = $value;
			}
		}
	}

	/**
	 * Get a configuration object representing this field to be passed to the UI
	 * Library
	 *
	 * @return array
	 */
	public function getConfig() {
		if (!$this->validate()) {
			fatalError('Form field configuration did not pass validation: ' . print_r($this, true));
		}
		$config = array(
			'name' => $this->name,
			'component' => $this->component,
			'label' => $this->label,
		);
		if (isset($this->description)) {
			$config['description'] = $this->description;
		}
		if (isset($this->tooltip)) {
			$config['tooltip'] = $this->tooltip;
		}
		if (isset($this->helpTopic)) {
			$config['helpTopic'] = $this->helpTopic;
			if ($this->helpSection) {
				$config['helpSection'] = $this->helpSection;
			}
		}
		if (isset($this->groupId)) {
			$config['groupId'] = $this->groupId;
		}
		if (isset($this->isRequired)) {
			$config['isRequired'] = $this->isRequired;
		}
		if (isset($this->isMultilingual)) {
			$config['isMultilingual'] = $this->isMultilingual;
		}
		if (isset($this->showWhen)) {
			$config['showWhen'] = $this->showWhen;
		}
		if (isset($this->value)) {
			$config['value'] = $this->value;
		} elseif (isset($this->default)) {
			$config['value'] = $this->default;
		}
		return $config;
	}

	/**
	 * Validate the field configuration
	 *
	 * Check that no required fields are missing
	 *
	 * @return boolean
	 */
	public function validate() {
		foreach ($this->_requiredProperties as $property) {
			if (!isset($this->{$property})) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get a default empty value for this field type
	 *
	 * The UI Library expects to receive a value property for each field. If it's
	 * a multilingual field, it expects the value property to contain keys for
	 * each locale in the form.
	 *
	 * This function will provide a default empty value so that a form can fill
	 * in the empty values automatically.
	 *
	 * @return mixed
	 */
	public function getEmptyValue() {
		return '';
	}
}
