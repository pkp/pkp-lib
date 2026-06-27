<?php
/**
 * @file classes/components/form/FieldMultiSelect.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldMultiSelect
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field to select multiple options from a dropdown.
 */

namespace PKP\components\forms;

class FieldMultiSelect extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-multi-select';

    /** @var array The options which can be selected */
    public $options = [];

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['options'] = $this->options;

        return $config;
    }

    /**
     * @copydoc Field::getEmptyValue()
     */
    public function getEmptyValue()
    {
        return [];
    }
}
