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
 * @brief A type of autosuggest field for controlled vocabulary like keywords.
 */
namespace PKP\components\forms;

use PKP\components\forms\FieldBaseAutosuggest;

class FieldControlledVocab extends FieldBaseAutosuggest {
	/** @copydoc Field::$component */
	public $component = 'field-controlled-vocab';

	/** @param array Key/value list of languages this field should support. Key = locale code. Value = locale name */
	public $locales = [];

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();

		if ($this->isMultilingual) {
			$config['selected'] = [];
			foreach ($this->locales as $locale) {
				if (array_key_exists($locale['key'], $this->selected)) {
					$config['selected'][$locale['key']] = array_map([$this, 'mapSelected'], (array) $this->selected[$locale['key']]);
				} else {
					$config['selected'][$locale['key']] = [];
				}
			}
		} else {
			$config['selected'] = array_map([$this, 'mapSelected'], $this->selected);
		}

		return $config;
	}

	/**
	 * Map the selected values to the format expected by an
	 * autosuggest field
	 *
	 * @param string value
	 * @return array
	 */
	public function mapSelected($value) {
		return [
			'value' => $value,
			'label' => $value,
		];
	}
}
