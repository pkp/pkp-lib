<?php
/**
 * @file classes/controllers/list/ListHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
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
    /**
     * Title (expects a translation key)
     *
     * @param string
     */
    public $_title = '';

    /**
     * Component path
     *
     * Used to generate component URLs. Sub-classes must define this.
     */
    public $_componentPath = '';

    /**
     * Routes
     *
     * Endpoints which can be requested by a URL. Defined by ::setRoutes()
     *
     * @param array
     */
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

        if (!empty($args['title'])) {
            $this->_title = $args['title'];
        }
    }

    /**
     * Retrieve the configuration data to be used when initializing this
     * handler on the frontend
     *
     * return array Configuration data
     */
    abstract function getConfig();
}
