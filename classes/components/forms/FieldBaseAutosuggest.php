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
     * Controlled vocabularies for selection, by locale.
     *
     * @var array[] $vocabularies
     * [
     *   [
     *     'locale'          => string,                     // e.g. 'en'
     *     'addButtonLabel'  => string,                     // e.g. 'Add subject'
     *     'modalTitleLabel'           => string,           // modal title
     *     'modalComponent'? => string,                     // custom component (default: VocabularyModal)
     *     'items'           => array[                       // tree of nodes
     *       [
     *         'label'      => string,                     // display text
     *         'value'      => int|array{                  // either simple ID (for FieldAutosuggestPreset which has predefined options, e.g. Categories )
     *                                                     // or full payload (for FieldAutosuggestControlledVocab)
     *                          identifier: string,         // code (e.g. '1.2')
     *                          name: string,               // display name
     *                          source?: string            // optional source
     *                        },
     *         'selectable'? => bool,                       // leaf nodes (default: false)
     *         'items'?      => array[]                     // child nodes
     *       ],
     *       …
     *     ]
     *   ],
     *   …
     * ]
     */
    public array $vocabularies = [];



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
