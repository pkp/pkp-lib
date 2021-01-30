<?php
/**
 * @file classes/components/form/context/PKPThemeForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPThemeForm
 * @ingroup classes_controllers_form
 *
 * @brief A form for selecting a theme and theme options. Expects to be attached
 *  to a <theme-form> element in the UI.
 *
 * This form works similarly to other form components, except that it keeps a
 * separate store of fields for each theme's options. Only the active theme's
 * fields are loaded into $this->fields. The <theme-form> UI component chooses
 * which fields to display as the theme selection is changed.
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldSelect;

define('FORM_THEME', 'theme');

class PKPThemeForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_THEME;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	/** @var array A key/value store of theme option fields, keyed by theme name */
	public $themeFields = [];

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $locales array Supported locales
	 * @param $context Context|null Journal/Press to change settings for, or null
	 *  to change settings for the Site
	 */
	public function __construct($action, $locales, $context = null) {
		$this->action = $action;
		$this->locales = $locales;

		if (!empty($context)) {
			$activeTheme = $context->getData('themePluginPath');
			$contextId = $context->getId();
		} else {
			$activeTheme = \Application::get()->getRequest()->getSite()->getData('themePluginPath');
			$contextId = CONTEXT_ID_NONE;
		}

		$themes = $themeOptions = [];
		$plugins = \PluginRegistry::loadCategory('themes', true);
		foreach ($plugins as $plugin) {
			$themes[] = [
				'value' => $plugin->getDirName(),
				'label' => $plugin->getDisplayName(),
			];
		}

		$this->addField(new FieldSelect('themePluginPath', [
				'label' => __('manager.setup.theme'),
				'description' => __('manager.setup.theme.description'),
				'options' => $themes,
				'value' => $activeTheme,
			]));

		// Add theme options for each theme
		foreach ($plugins as $plugin) {
			// Re-run the init functions for each theme so that any theme options
			// are set up. Because this is run after PluginRegistry::loadCategory(),
			// the scripts and styles won't actually be registered against the
			// template manager. However, if PluginRegistry::loadCategory() is called
			// again for the themes category, it can cause scripts and styles to be
			// overwritten by inactive themes.
			$plugin->init();
			$themeOptions = $plugin->getOptionsConfig();
			if (empty($themeOptions)) {
				continue;
			}
			$themeOptionValues = $plugin->getOptionValues($contextId);
			foreach ($themeOptions as $optionName => $optionField) {
				$optionField->value = isset($themeOptionValues[$optionName]) ? $themeOptionValues[$optionName] : null;
				$this->addThemeField($plugin->getDirName(), $optionField);
			}
		}
	}

	/**
	 * Add a form field that should only appear when a particular theme is
	 * selected
	 *
	 * @param $theme string The theme's base plugin path
	 * @param $field Field
	 * @param $position array [
	 *  @option string One of `before` or `after`
	 *  @option string The field to position it before or after
	 * ]
	 * @return FormComponent
	 */
	public function addThemeField($theme, $field, $position = []) {
		if (empty($position)) {
			if (!isset($this->themeFields[$theme])) {
				$this->themeFields[$theme] = [];
			}
			$this->themeFields[$theme][] = $field;
		} else {
			$this->themeFields[$theme] = $this->addToPosition($position[1], $this->themeFields[$theme], $field, $position[0]);
		}
		return $this;
	}

	/**
	 * @copydoc FormComponent::getConfig()
	 */
	public function getConfig() {
		// Add the active theme's option fields to the fields array
		$activeThemeField = array_filter($this->fields, function($field) {
			return $field->name === 'themePluginPath';
		});
		$activeTheme = $activeThemeField[0]->value;
		if (!empty($this->themeFields[$activeTheme])) {
			$this->fields = array_merge($this->fields, $this->themeFields[$activeTheme]);
		}

		$config = parent::getConfig();

		// Set up field config for non-active fields
		if (!$this->groups) {
			$this->addGroup(array('id' => 'default'));
			$this->fields = array_map(function($field) {
				$field->groupId = 'default';
				return $field;
			}, $this->fields);
		}
		$defaultGroupId = $this->groups[0]['id'];
		$config['themeFields'] = array_map(function($themeOptions) use ($defaultGroupId) {
			return array_map(function($themeOption) use ($defaultGroupId) {
				$field = $this->getFieldConfig($themeOption);
				$field['groupId'] = $defaultGroupId;
				return $field;
			}, $themeOptions);
		}, $this->themeFields);

		return $config;
	}
}
