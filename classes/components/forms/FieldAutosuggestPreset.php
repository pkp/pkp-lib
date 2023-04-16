<?php
/**
 * @file classes/components/form/FieldControlledVocab.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldAutosuggestPreset
 *
 * @ingroup classes_controllers_form
 *
 * @brief A type of autosuggest field that preloads all of its options.
 */

namespace PKP\components\forms;

class FieldAutosuggestPreset extends FieldBaseAutosuggest
{
    /** @copydoc Field::$component */
    public $component = 'field-autosuggest-preset';

    /** @var array Key/value list of suggestions for this field */
    public $options = [];

    /** @var array Key/value list of languages this field should support. Key = locale code. Value = locale name */
    public $locales = [];

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['options'] = $this->options;
        $config['selected'] = $this->getSelected();

        return $config;
    }

    /**
     * @copydoc Field::getConfig()
     */
    protected function getSelected(): array
    {
        if ($this->isMultilingual) {
            $selected = [];
            foreach ($this->locales as $locale) {
                if (array_key_exists($locale['key'], $this->value)) {
                    $config['selected'][$locale['key']] = array_map([$this, 'mapSelected'], (array) $this->value[$locale['key']]);
                } else {
                    $config['selected'][$locale['key']] = [];
                }
            }
            return $selected;
        }

        return array_map([$this, 'mapSelected'], $this->value);
    }

    /**
     * Map the selected values to the format expected by an
     * autosuggest field
     *
     * @param string $value
     *
     * @return array
     */
    protected function mapSelected($value)
    {
        foreach ($this->options as $option) {
            if ($option['value'] === $value) {
                return $option;
            }
        }
        return [
            'value' => $value,
            'label' => $value,
        ];
    }
}
