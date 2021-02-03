<?php
/**
 * @file components/listPanels/ListPanel.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ListPanel
 * @ingroup classes_components_list
 *
 * @brief A base class for ListPanel components.
 */

namespace PKP\components\listPanels;

class ListPanel {

	/** @var string An optional description to add beneath the title */
	public $description = '';

	/** @var string An optional message to display when no items are in the list */
	public $emptyLabel = '';

	/** @var array Array of item ids that should be expanded on initial load  */
	public $expanded = [];

	/** @var string The appropriate heading level for this component  */
	public $headingLevel = 'h2';

	/** @var boolean Should the sidebar be visible on initial load?  */
	public $isSidebarVisible = false;

	/** @var string An ID for this component  */
	public $id = '';

	/** @var array Items to display in the list */
	public $items = [];

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
			'description' => $this->description,
			'expanded' => $this->expanded,
			'headingLevel' => $this->headingLevel,
			'id' => $this->id,
			'isSidebarVisible' => $this->isSidebarVisible,
			'items' => $this->items,
			'title' => $this->title,
		];

		if (strlen($this->emptyLabel)) {
			$config['emptyLabel'] = $this->emptyLabel;
		}

		if (!empty($this->getParams)) {
			$config['getParams'] = $this->getParams;
		}

		return $config;
	}
}
