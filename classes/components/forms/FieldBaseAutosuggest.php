<?php
/**
 * @file classes/components/form/FieldBaseAutosuggest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldBaseAutosuggest
 *
 * @ingroup classes_controllers_form
 *
 * @brief A base class for text fields that provide suggested values while typing.
 */

namespace PKP\components\forms;

define('AUTOSUGGEST_POSITION_INLINE', 'inline');
define('AUTOSUGGEST_POSITION_BELOW', 'below');

abstract class FieldBaseAutosuggest extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-base-autosuggest';

    /** @var string A URL to retrieve suggestions. */
    public $apiUrl;

    /** @var array Query params when getting suggestions. */
    public $getParams = [];

    /** @var array List of selected items. */
    public $selected = [];

    /**
     * @var array List of controlled vocabularies available for selection.
     * Each vocabulary should be structured as:
     * [
     *   'locale' => string  // The locale this vocabulary applies to (e.g., 'en')
     *   'addButtonLabel' => string  // Label for the button to add from this vocabulary
     *   'title' => string  // Title to display in the vocabulary selection modal
     *   'modalComponent' => string  // (Optional) Custom modal component to use for this vocabulary. Defaults to VocabularyModal
     *   'items' => [  // Hierarchical tree of vocabulary items
     *     [
     *       'identifier' => string  // Unique identifier for the vocabulary item
     *       'name' => string  // Display name of the vocabulary item
     *       'source' => string  // (Optional) Source of the vocabulary (e.g., 'Frascati')
     *       'items' => [  // (Optional) Child items for hierarchical vocabularies
     *         // Each child follows the same structure (identifier, name, source, items)
     *       ]
     *     ],
     *     // Additional top-level vocabulary items...
     *   ]
     * ]
     */
    public $vocabularies = [];

    

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['apiUrl'] = $this->apiUrl;
        $config['deselectLabel'] = __('common.removeItem');
        $config['getParams'] = empty($this->getParams) ? new \stdClass() : $this->getParams;
        $config['selectedLabel'] = __('common.selectedPrefix');
        $config['selected'] = $this->selected;
        $config['vocabularies'] = $this->vocabularies;

        return $config;
    }
}
