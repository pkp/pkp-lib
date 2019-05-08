<?php
/**
 * @file components/listPanels/ListPanel.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ListPanel
 * @ingroup classes_components_list
 *
 * @brief A base class for ListPanel components.
 */

namespace PKP\components\listPanels;
use PKP\components\listPanels;

class ListPanel {
	/** @var string URL to the API endpoint where items can be retrieved */
	public $apiUrl = '';

	/** @var bool Whether items in this list can be selected */
	public $canSelect = false;

	/** @var bool Whether this list should have a select all button */
	public $canSelectAll = false;

	/** @var int How many items to display on one page in this list */
	public $count = 30;

	/** @var string An optional description to add beneath the title */
	public $description = '';

	/** @var array List of filters available for the list  */
	public $filters = [];

	/** @var array Query parameters to pass if this list executes GET requests  */
	public $getParams = [];

	/** @var string An ID for this component  */
	public $id = '';

	/** @var array Items to display in the list */
	public $items = [];

	/** @var array Maximum item available in this list */
	public $itemsMax = 0;

	/** @var bool Whether to pre-populate the UI component with list data or wait until the page has loaded to request the data. */
	public $lazyLoad = false;

	/** @var array Item values which should appear already selected */
	public $selected = [];

	/** @var string An optional name for the selection input field. */
	public $selectorName = '';

	/** @var string The type of input field for selection. Accepts checkbox or radio */
	public $selectorType = 'checkbox';

	/** @var string Title (expects a translation key) */
	public $title = '';

	/**
	 * Initialize the form with config parameters
	 *
	 * @param $id string
	 * @param $title string
	 * @param $args array Configuration params
	 */
	function __construct($id, $title, $args = []) {
		$this->id = $id;
		$this->title = $title;
		$this->set($args);
	}

	/**
	 * Set configuration data for the component
	 *
	 * @param $args array Configuration params
	 * @return
	 */
	public function set($args) {
		foreach ($args as $prop => $value) {
			if (property_exists($this, $prop)) {
				$this->{$prop} = $value;
			}
		}

		return $this;
	}

	/**
	 * Convert the object into an assoc array ready to be json_encoded
	 * and passed to the UI component
	 *
	 * @return array Configuration data
	 */
	public function getConfig() {
		$config = [
			'apiUrl' => $this->apiUrl,
			'canSelect' => $this->canSelect,
			'canSelectAll' => $this->canSelectAll,
			'count' => $this->count,
			'description' => $this->description,
			'filters' => $this->filters,
			'id' => $this->id,
			'items' => $this->items,
			'itemsMax' => $this->itemsMax,
			'lazyLoad' => $this->lazyLoad,
			'offset' => 0,
			'selected' => $this->selected,
			'selectorName' => $this->selectorName,
			'selectorType' => $this->selectorType,
			'title' => $this->title,
			'i18n' => [
				'clearSearch' => __('common.clearSearch'),
				'empty' => __('common.noItemsFound'),
				'filter' => __('common.filter'),
				'filterRemove' => __('common.filterRemove'),
				'loading' => __('common.loading'),
				'search' => __('common.search'),
				'selectAllLabel' => __('common.selectAll'),
			]
		];

		if (!empty($this->getParams)) {
			$config['getParams'] = $this->getParams;
		}

		if ($this->lazyLoad) {
			$config['items'] = [];
			$config['itemsMax'] = 0;
		}

		return $config;
	}
}
