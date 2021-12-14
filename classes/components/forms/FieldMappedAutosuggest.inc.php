<?php
/**
 * @file classes/components/form/FieldMappedAutosuggest.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldMappedAutosuggest
 * @ingroup classes_controllers_form
 *
 * @brief A type of autosuggest field that can request large result sets from an API and display an iteration based on load more.
 */

namespace PKP\components\forms;

class FieldMappedAutosuggest extends FieldBaseAutosuggest
{
    /** @copydoc Field::$component */
    public $component = 'field-mapped-autosuggest';

    /**
     * An optional JavaScript function which will receive each item from the API response and should map 
     * them to an object {label: "label", value: "value"}
     */
    public ?string $dataMapper = null;

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['dataMapper'] = $this->dataMapper;

        return $config;
    }
}
