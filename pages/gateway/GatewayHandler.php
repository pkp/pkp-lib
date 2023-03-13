<?php

/**
 * @file pages/gateway/GatewayHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GatewayHandler
 * @ingroup pages_gateway
 *
 * @brief Handle external gateway requests.
 */

namespace APP\pages\gateway;

use APP\core\PageRouter;
use APP\handler\Handler;
use PKP\plugins\PluginRegistry;

class GatewayHandler extends Handler
{
    public $plugin;

    /**
     * Constructor
     *
     * @param \PKP\core\PKPRequest $request
     */
    public function __construct($request)
    {
        parent::__construct();
        /** @var PageRouter */
        $router = $request->getRouter();
        $op = $router->getRequestedOp($request);
        if ($op == 'plugin') {
            $args = $router->getRequestedArgs($request);
            $pluginName = array_shift($args);
            $plugins = PluginRegistry::loadCategory('gateways');
            if (!isset($plugins[$pluginName])) {
                $request->getDispatcher()->handle404();
            }
            $this->plugin = $plugins[$pluginName];
            foreach ($this->plugin->getPolicies($request) as $policy) {
                $this->addPolicy($policy);
            }
        }
    }

    /**
     * Index handler.
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     */
    public function index($args, $request)
    {
        $request->redirect(null, 'index');
    }

    /**
     * Handle requests for gateway plugins.
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     */
    public function plugin($args, $request)
    {
        $this->validate();
        if (isset($this->plugin)) {
            if (!$this->plugin->fetch(array_slice($args, 1), $request)) {
                $request->redirect(null, 'index');
            }
        } else {
            $request->redirect(null, 'index');
        }
    }
}
