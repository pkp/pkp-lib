<?php
/**
 * @file classes/components/form/FormComponent.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormComponent
 * @ingroup classes_controllers_form
 *
 * @brief A base class for building forms to be passed to the Form component
 *  in the UI Library.
 */
namespace PKP\components\forms;
use PKP\components\forms;

define('FIELD_POSITION_BEFORE', 'before');
define('FIELD_POSITION_AFTER', 'after');

class FormComponent {
	/** @var string A unique ID for this form */
	public $id = '';

	/** @var string Form method: POST or PUT */
	public $method = '';

	/** @var string Where the form should be submitted. */
	public $action = '';

	/** @var string The message to display when this form is successfully submitted */
	public $successMessage = '';

	/** @var array Key/value list of languages this form should support. Key = locale code. Value = locale name */
	public $locales = [];

	/** @var array List of fields in this form. */
	public $fields = [];

	/** @var array List of groups in this form. */
	public $groups = [];

	/** @var array List of pages in this form. */
	public $pages = [];

	/** @var array List of error messages */
	public $errors = [];

	/** @var array List of translation strings required by this form */
	public $i18n = [];

	/**
	 * Initialize the form with config parameters
	 *
	 * @param $id string
	 * @param $method string
	 * @param $action string
	 * @param $locales array
	 * @param $i18n array Optional.
	 */
	public function __construct($id, $method, $action, $successMessage, $locales, $i18n = []) {
		$this->id = $id;
		$this->action = $action;
		$this->method = $method;
		$this->successMessage = $successMessage;
		$this->locales = $locales;
		$this->i18n = $i18n;
	}

	/**
	 * Add a form field
	 *
	 * @param $field Field
	 * @param $position array [
	 *  @option string One of FIELD_POSITION_BEFORE or FIELD_POSITION_AFTER
	 *  @option string The field to position it before or after
	 * ]
	 * @return FormComponent
	 */
	public function addField($field, $position = []) {
		if (empty($position)) {
			$this->fields[] = $field;
		} else {
			$this->fields = $this->addToPosition($position[1], $this->fields, $field, $position[0]);
		}
		return $this;
	}

	/**
	 * Remove a form field
	 *
	 * @param $fieldName string
	 * @return FormComponent
	 */
	public function removeField($fieldName) {
		$this->fields = array_filter($this->fields, function($field) use ($fieldName) {
			return $field->name !== $fieldName;
		});
		return $this;
	}

	/**
	 * Get a form field
	 *
	 * @param $fieldName string
	 * @return Field
	 */
	public function getField($fieldName) {
		foreach ($this->fields as $field) {
			if ($field->name === $fieldName) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * Add a form group
	 *
	 * @param $args array [
	 *  @option id string Required A unique ID for this form group
	 *  @option label string A label to identify this group of fields. Will become the fieldset's <legend>
	 *  @option description string A description of this group of fields.
	 * ]
	 * @param $position array [
	 *  @option string One of FIELD_POSITION_BEFORE or FIELD_POSITION_AFTER
	 *  @option string The group to position it before or after
	 * ]
	 * @return FormComponent
	 */
	public function addGroup($args, $position = []) {
		if (empty($args['id'])) {
			fatalError('Tried to add a form group without an id.');
		}
		if (empty($position)) {
			$this->groups[] = $args;
		} else {
			$this->groups = $this->addToPosition($position[1], $this->groups, $args, $position[0]);
		}
		return $this;
	}

	/**
	 * Remove a form group
	 *
	 * @param $groupId string
	 * @return FormComponent
	 */
	public function removeGroup($groupId) {
		$this->groups = array_filter($this->groups, function($group) use ($groupId) {
			return $group['id'] !== $groupId;
		});
		$this->fields = array_filter($this->fields, function($field) use ($groupId) {
			return $field['groupId'] !== $groupId;
		});
		return $this;
	}

	/**
	 * Add a form page
	 *
	 * @param $args array [
	 *  @option id string Required A unique ID for this form page
	 *  @option label string The name of the page to identify it in the page list
	 *  @option submitButton array Required Assoc array defining submission/next button params. Supports any param of the Button component in the UI Library.
	 *  @option previousButton array Assoc array defining button params to go back to the previous page. Supports any param of the Button component in the UI Library.
	 * ]
	 * @param $position array [
	 *  @option string One of FIELD_POSITION_BEFORE or FIELD_POSITION_AFTER
	 *  @option string The page to position it before or after
	 * ]
	 * @return FormComponent
	 */
	public function addPage($args, $position = []) {
		if (empty($args['id'])) {
			fatalError('Tried to add a form page without an id.');
		}
		if (empty($position)) {
			$this->pages[] = $args;
		} else {
			$this->pages = $this->addToPosition($position[1], $this->pages, $args, $position[0]);
		}
		return $this;
	}

	/**
	 * Remove a form page
	 *
	 * @param $pageId string
	 * @return FormComponent
	 */
	public function removePage($pageId) {
		$this->pages = array_filter($this->pages, function($page) use ($pageId) {
			return $page['id'] !== $pageId;
		});
		foreach ($this->groups as $group) {
			if ($group['pageId'] === $pageId) {
				$this->removeGroup($group['id']);
			}
		}
		return $this;
	}

	/**
	 * Add an field, group or page to a specific position in its array
	 *
	 * @param $id string The id of the item to position before or after
	 * @param $list array The list of fields, groups or pages
	 * @param $item array The item to insert
	 * @param $position string FIELD_POSITION_BEFORE or FIELD_POSITION_AFTER
	 * @return array
	 */
	public function addToPosition($id, $list, $item, $position) {
		$index = count($list);
		foreach ($list as $key => $val) {
			if ((is_a($val, 'PKP\components\forms\Field') && $id === $val->name) || (!is_a($val, 'PKP\components\forms\Field') && $id === $val['id'])) {
				$index = $key;
				break;
			}
		}
		if (!$index && $position === FIELD_POSITION_BEFORE) {
			array_unshift($list, $item);
			return $list;
		}

		$slice = $position === FIELD_POSITION_BEFORE ? $index : $index + 1;

		return array_merge(
			array_slice($list, 0, $slice),
			[$item],
			array_slice($list, $slice)
		);
	}

	/**
	 * Retrieve the configuration data to be used when initializing this
	 * handler on the frontend
	 *
	 * @return array Configuration data
	 */
	public function getConfig() {

		if (empty($this->id) || empty($this->method) || empty($this->action) || empty($this->successMessage) || empty($this->fields)) {
			fatalError('FormComponent::getConfig() was called but one or more required property is missing: id, method, action, successMessage, fields.');
		}

		\HookRegistry::call('Form::config::before', $this);

		// Add a default page/group if none exist
		if (!$this->groups) {
			$this->addGroup(array('id' => 'default'));
			$this->fields = array_map(function($field) {
				$field->groupId = 'default';
				return $field;
			}, $this->fields);
		}

		if (!$this->pages) {
			$this->addPage(array('id' => 'default', 'submitButton' => array('label' => __('common.save'))));
			$this->groups = array_map(function($group) {
				$group['pageId'] = 'default';
				return $group;
			}, $this->groups);
		}

		$fieldsConfig = array_map([$this, 'getFieldConfig'], $this->fields);

		$session = \Application::get()->getRequest()->getSession();
		$csrfToken = $session ? $session->getCSRFToken() : '';

		$this->i18n = array_merge([
			'saving' => __('common.saving'),
			'errors' => __('form.errors'),
			'errorOne' => __('form.errorOne'),
			'errorMany' => __('form.errorMany'),
			'errorGoTo' => __('form.errorGoTo'),
			'errorA11y' => __('form.errorA11y'),
			'errorUnknown' => __('form.errorUnknown'),
			'successMessage' => $this->successMessage,
			'required' => __('common.required'),
			'missingRequired' => __('validator.required'),
			'help' => __('common.help'),
			'multilingualLabel' => __('form.multilingualLabel'),
			'multilingualProgress' => __('form.multilingualProgress'),
		], $this->i18n);

		$config = array(
			'id' => $this->id,
			'method' => $this->method,
			'action' => $this->action,
			'fields' => $fieldsConfig,
			'groups' => $this->groups,
			'pages' => $this->pages,
			'primaryLocale' => \AppLocale::getPrimaryLocale(),
			'visibleLocales' => [\AppLocale::getLocale()],
			'supportedFormLocales' => $this->locales,
			'errors' => new \stdClass(),
			'csrfToken' => $csrfToken,
			'i18n' => $this->i18n,
		);

		\HookRegistry::call('Form::config::after', array(&$config, $this));

		return $config;
	}

	/**
	 * Compile a configuration array for a single field
	 *
	 * @param $field Field
	 * @return array
	 */
	public function getFieldConfig($field) {
		$config = $field->getConfig();

		// Pass all field translations up to the form
		if (!empty($field->i18n)) {
			$this->i18n = array_merge($this->i18n, $field->i18n);
		}

		// Add a value property if the field does not include one
		if (!array_key_exists('value', $config)) {
			$config['value'] = $field->isMultilingual ? array() : $field->getEmptyValue();
		}
		if ($field->isMultilingual) {
			foreach ($this->locales as $locale) {
				if (!array_key_exists($locale['key'], $config['value'])) {
					$config['value'][$locale['key']] = $field->getEmptyValue();
				}
			}
		}

		return $config;
	}
}
