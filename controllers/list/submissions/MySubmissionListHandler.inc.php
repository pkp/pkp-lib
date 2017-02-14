<?php
/**
 * @file classes/controllers/list/submissions/MySubmissionListHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MySubmissionListHandler
 * @ingroup classes_controllers_list
 */

import('classes.article.ArticleDAO');

class MySubmissionListHandler extends PKPHandler {
    /**
     * Title (expects a translation key)
     *
     * @param string
     */
    public $_title = '';

    /**
     * Component path
     *
     * Used to generate component URLs
     */
    public $_componentPath = 'list.submissions.MySubmissionListHandler';

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

        $this->setRoutes();

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

        if (!empty( $args['title'])) {
            $this->_title = $args['title'];
        }
    }

    /**
     * Define the routes this component supports
     *
     * @return array Routes supported by this component
     */
    public function setRoutes() {

        $this->addRoute('get', array(
            'methods' => array('GET'),
            'roleAccess' => array(
                ROLE_ID_SITE_ADMIN,
                ROLE_ID_MANAGER,
                ROLE_ID_SUB_EDITOR,
                ROLE_ID_AUTHOR,
                ROLE_ID_REVIEWER,
                ROLE_ID_ASSISTANT,
            ),
        ));

        return $this->_routes;
    }

    /**
     * Add a new route for this component
     *
     * @param string $router The component's route that should be invoked
     * @param array $args Configuration params for this route
     *  `methods` array Supported request methods (GET|POST)
     *  `roleAccess` array Roles allowed to access this route
     *  `url` string Optional
     * @return bool
     */
    public function addRoute($router, $args) {

        if (empty($args['methods'])) {
            error_log("Handler $router is missing a methods key in " . get_class($this));
            return false;
        }

        if (empty($args['roleAccess']) || !is_array($args['roleAccess'])) {
            error_log("Handler $router is missing a roleAccess key in " . get_class($this));
            return false;
        }

        if (empty($args['url'])) {
            $request = Application::getRequest();
            $args['url'] = $request->getDispatcher()->url(
                $request,
                ROUTE_COMPONENT,
                null,
                $this->_componentPath,
                $router
            );
        }

        $this->_routes[$router] = $args;

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
            'config' => array(
                'routes' => $this->_routes,
            ),
            'i18n' => array(
                'title' => __($this->_title),
                'view' => __('submission.list.view'),
                'item_count' => __('submission.list.count'),
            ),
        );
    }

    /**
     * API Route: Get all submissions assigned to author
     *
     * @param array $args None supported at this time
     * @param Request $request
     */
    public function get($args, $request) {
        echo json_encode($this->getItems());
        exit();
    }

    /**
     * Helper function to retrieve all items assigned to the author
     *
     * @param array $args None supported at this time
     * @return array Items requested
     */
    public function getItems($args = array()) {

        $submissionDao = Application::getSubmissionDAO();
        $request = Application::getRequest();
        $user = $request->getUser();

        $submissions = $submissionDao->getAssignedToUser($user->getId())->toArray();

        $items = array();
        foreach($submissions as $submission) {
            $items[] = array(
                'title' => $submission->getLocalizedTitle(),
                'author' => $submission->getAuthorString(),
                'dateSubmitted' => $submission->getDateSubmitted(),
            );
        }

        return $items;
    }
}
