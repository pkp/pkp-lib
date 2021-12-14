<?php
/**
 * @file classes/components/form/FieldBaseAutosuggest.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldBaseAutosuggest
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

    /** @var string Displayed in the text box or below the input. One of the AUTOSUGGEST_POSITION_* constants. */
    public $initialPosition = AUTOSUGGEST_POSITION_INLINE;

    /** @var array List of selected items. */
    public $selected = [];

    /** The number of items to return with each request. */
    public int $count = 30;

    /** Pass true to fire off a request for items after the component is mounted. */
    public bool $lazyLoad = false;

    /** Defines the minimum amount of characters to trigger the API request. */
    public int $minInputLength = 0;

    /** Defines the maximum amount of items that can be selected. */
    public ?int $maxSelectedItems = null;

    /**
     * Defines the behavior of selecting an additional item when the maxSelectedItems has been reached.
     * True: The new item replaces the last item.
     * False: Blocks adding the new item.
     */
    public bool $replaceWhenFull = true;

    /** Defines the maximum height of the list, if the threshold is exceeded, a scrollbar will be added. If null, there will be no limits. */
    public string $maxHeight = '260px';

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['apiUrl'] = $this->apiUrl;
        $config['deselectLabel'] = __('common.removeItem');
        $config['getParams'] = empty($this->getParams) ? new \stdClass() : $this->getParams;
        $config['initialPosition'] = $this->initialPosition;
        $config['selectedLabel'] = __('common.selectedPrefix');
        $config['selected'] = $this->selected;
        $config['count'] = $this->count;
        $config['lazyLoad'] = $this->lazyLoad;
        $config['minInputLength'] = $this->minInputLength;
        $config['maxSelectedItems'] = $this->maxSelectedItems;
        $config['replaceWhenFull'] = $this->replaceWhenFull;
        $config['maxHeight'] = $this->maxHeight;

        return $config;
    }
}
