<?php
/**
 * @file controllers/list/ListHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A base class for list handlers. This defines the structure that will
 *  be used by handlers which need to present, manage or edit a list of items.
 */
abstract class ListHandler extends PKPHandler {
	/** @var string Title (expects a translation key) */
	public $_title = '';

	/** @var bool Whether to pre-populate the UI component with list data or wait until the page has loaded to request the data. */
	public $_lazyLoad = false;

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
	 * @param $args array Configuration params
	 */
	public function init($args = array()) {
		$this->setId(!empty($args['id']) ? $args['id'] : get_class($this));
		$this->_title = !empty($args['title']) ? $args['title'] : $this->_title;
		$this->_lazyLoad = !empty($args['lazyLoad']);
	}

	/**
	 * Retrieve the configuration data to be used when initializing this
	 * handler on the frontend
	 *
	 * @return array Configuration data
	 */
	abstract function getConfig();

	/**
	 * Helper function to retrieve items
	 *
	 * @return array Items requested
	 */
	public function getItems() {
		fatalError('ListHandler::getItems() must be implemented in a child class. Attempted to use it in ' . get_class($this));
	}

	/**
	 * Helper function to retrieve itemsMax count
	 *
	 * @return int Max items that can be retrieved with current query parms
	 */
	public function getItemsMax() {
		fatalError('ListHandler::getItems() must be implemented in a child class. Attempted to use it in ' . get_class($this));
	}

	/**
	 * Helper function to compile item params for self::getItems and
	 * self::getItemsMax
	 *
	 * @return array
	 */
	protected function _getItemsParams() {
		fatalError('ListHandler::_getItemsParams() must be implemented in a child class. Attempted to use it in ' . get_class($this));
	}
}
