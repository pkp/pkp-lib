<?php
/**
 * @file controllers/list/SelectListHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A base class for list selection handlers. This defines the structure
 *  that will be used by handlers which need to select from a list of items.
 */
class SelectListHandler extends PKPHandler {
	/** @var string Title (expects a translation key) */
	public $_title = '';

	/** @var string A notice to display above items (expects translation key) */
	public $_notice = '';

	/** @var string Input field name */
	public $_inputName = '';

	/** @var string Input field type (checkbox or radio) */
	public $_inputType = 'checkbox';

	/** @var array Pre-selected input values */
	public $_selected = array();

	/** @var mixed Items to select from */
	public $_items = null;

	/**
	 * Constructor
	 */
	function __construct($args = array()) {
		parent::__construct();

		$this->init($args);
	}

	/**
	 * Initialize the handler with config parameters
	 *
	 * @param array $args Configuration params
	 */
	public function init($args = array()) {
		$this->setId(!empty($args['id']) ? $args['id'] : get_class($this));
		$this->_title = !empty($args['title']) ? $args['title'] : $this->_title;
		$this->_notice = !empty($args['notice']) ? $args['notice'] : $this->_notice;
		$this->_inputName = !empty($args['inputName']) ? $args['inputName'] : $this->_inputName;
		$this->_inputType = !empty($args['inputType']) ? $args['inputType'] : $this->_inputType;
		$this->_selected = !empty($args['selected']) ? $args['selected'] : $this->_selected;
		$this->_items = !empty($args['items']) ? $args['items'] : $this->_items;
	}

	/**
	 * Retrieve the configuration data to be used when initializing this
	 * handler on the frontend
	 *
	 * @return array Configuration data
	 */
	public function getConfig() {

		if (is_null($this->_items)) {
			$this->_items = $this->getItems();
		}

		$data = array(
			'inputName' => $this->_inputName,
			'inputType' => $this->_inputType,
			'selected' => $this->_selected,
			'collection' => array(
				'items' => $this->_items,
			),
			'i18n' => array(
				'title' => __($this->_title),
			),
		);

		if ($this->_notice) {
			$data['i18n']['notice'] = __($this->_notice);
		}

		return $data;
	}

	/**
	 * Helper function to retrieve items
	 *
	 * @return array Items requested
	 */
	public function getItems() {
		fatalError('SelectListHandler must be instantiated with items or child classes must implement getItems().');
	}
}
