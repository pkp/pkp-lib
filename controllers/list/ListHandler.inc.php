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
        $this->setRoutes($args);

        foreach($this->_routes as $routerId => $router) {
            $this->addRoleAssignment($router['roleAccess'], $routerId);
        }
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
     * Define the routes this component supports
     *
     * @return array Routes supported by this component
     */
    abstract function setRoutes();

    /**
     * Add a new route for this component
     *
     * @param string $route The component's route that should be added
     * @param array $args Configuration params for this route
     *  `methods` array Supported request methods (GET|POST)
     *  `roleAccess` array Roles allowed to access this route
     *  `url` string Optional
     * @return bool
     */
    public function addRoute($route, $args) {

		if (!method_exists($this, $route)) {
			error_log("No method exists for route $route in " . get_class($this));
			return false;
		}

        if (empty($args['methods'])) {
            error_log("Handler $route is missing a methods key in " . get_class($this));
            return false;
        }

        if (empty($args['roleAccess']) || !is_array($args['roleAccess'])) {
            error_log("Handler $route is missing a roleAccess key in " . get_class($this));
            return false;
        }

        if (empty($args['url'])) {
            $request = Application::getRequest();
            $args['url'] = $request->getDispatcher()->url(
                $request,
                ROUTE_COMPONENT,
                null,
                $this->_componentPath,
                $route
            );
        }

        $this->_routes[$route] = $args;

        return true;
    }

    /**
     * Modify an existing route.
     *
     * @param string $route The component's route that should be modified
     * @param array $args Configuration params to modify in this route.
     * @see addRoute for params that can be modified.
     * @return bool Success or failure
     */
    public function modifyRoute($route, $args) {

        if (empty($this->_routes[$route])) {
            error_log("No route named $route exists to be modified in " . get_class($this));
            return false;
        }

        if (!empty($args['methods'])) {
            $this->_routes[$route]['methods'] = $args['methods'];
        }

        if (!empty($args['roleAccess'])) {
            $this->_routes[$route]['roleAccess'] = $args['roleAccess'];
        }

        if (!empty($args['url'])) {
            $this->_routes[$route]['url'] = $args['url'];
        }

        return true;
    }

    /**
     * Delete an existing route
     *
     * @param string $route The component's route to delete
     * @return bool
     */
    public function deleteRoute($route) {

        if (empty($this->_routes[$route])) {
            error_log("No route named $route exists to be deleted in " . get_class($this));
            return false;
        }

        unset($this->_routes[$route]);

        return true;
    }

    /**
     * Retrieve the configuration data to be used when initializing this
     * handler on the frontend
     *
     * return array Configuration data
     */
    public function getConfig() {

        return array(
            'id' => $this->getId(),
            'items' => $this->getItems(),
            'searchPhrase' => '',
            'isLoading' => false,
            'isSearching' => false,
            'config' => array(
                'routes' => $this->_routes,
            ),
            'i18n' => array(
                'title' => __($this->_title),
            ),
        );
    }

    /**
     * Helper function to retrieve all items assigned to the author
     *
     * Sub-classes should use this to pre-populate the list with data.
     *
     * @param array $args None supported at this time
     * @return array Items requested
     */
    public function getItems($args = array()) {
        return array();
    }
}
