<?php

/**
 * @file classes/components/form/FieldOptions.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldOptions
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field to select from a set of checkbox or radio options.
 */

namespace PKP\components\forms;

class FieldOptions extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-options';

    /** @var string Use a checkbox or radio button input type */
    public $type = 'checkbox';

    /** @var bool Should the user be able to re-order the options? */
    public $isOrderable = false;

    /** @var bool Should the user be able to only sort the options?
     * In this case the input element (checkbox or radio button) is not displayed.
     */
    public $allowOnlySorting = false;

    /** @var array The options which can be selected */
    public $options = [];

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['type'] = $this->type;
        $config['isOrderable'] = $this->isOrderable;
        $config['allowOnlySorting'] = $this->allowOnlySorting;
        $config['options'] = $this->options;

        return $config;
    }

    /**
     * @copydoc Field::getEmptyValue()
     *
     * The value's runtime type is what selects the input mode client-side, so
     * the empty value must match the mode the field was written for. A single
     * checkbox whose option value is a boolean is a boolean on/off toggle, so
     * its empty value is `false`; any other checkbox is a multi-select group,
     * so its empty value is `[]`. For this detection to work, a boolean
     * toggle's option must declare a real boolean value, e.g.
     * `'options' => [['value' => true, 'label' => ...]]`.
     */
    public function getEmptyValue()
    {
        if ($this->type === 'radio') {
            return '';
        }
        $option = count($this->options) === 1 ? reset($this->options) : null;
        return is_bool($option['value'] ?? null) ? false : [];
    }
}
