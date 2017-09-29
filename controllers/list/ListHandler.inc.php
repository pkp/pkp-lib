<?php
/**
 * @file controllers/list/ListHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2016 John Willinsky
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
	 * Component path
	 *
	 * Used to generate component URLs. Sub-classes must define this.
	 */
	public $_componentPath = '';

	/** @var array Endpoints which can be requested by a URL. Defined by ::setRoutes() */
	public $_routes = array();

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
		$this->_lazyLoad = !empty($args['lazyLoad']);
	}

	/**
	 * Retrieve the configuration data to be used when initializing this
	 * handler on the frontend
	 *
	 * return array Configuration data
	 */
	abstract function getConfig();
}
