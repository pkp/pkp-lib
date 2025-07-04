<?php

/**
 * @file classes/security/authorization/HandlerOperationPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HandlerOperationPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Abstract base class that provides infrastructure
 *  to control access to handler operations.
 */

namespace PKP\security\authorization;

class HandlerOperationPolicy extends AuthorizationPolicy
{
    /** @var \PKP\core\PKPRequest */
    public $_request;

    /** @var array the target operations */
    public $_operations = [];

    /**
     * Constructor
     *
     * @param \PKP\core\PKPRequest $request
     * @param array|string $operations either a single operation or a list of operations that
     *  this policy is targeting.
     * @param string $message a message to be displayed if the authorization fails
     */
    public function __construct($request, $operations, $message = null)
    {
        parent::__construct($message);
        $this->_request = & $request;

        // Make sure a single operation doesn't have to
        // be passed in as an array.
        assert(is_string($operations) || is_array($operations));
        if (!is_array($operations)) {
            $operations = [$operations];
        }
        $this->_operations = $operations;
    }


    //
    // Setters and Getters
    //
    /**
     * Return the request.
     *
     * @return \PKP\core\PKPRequest
     */
    public function &getRequest()
    {
        return $this->_request;
    }

    /**
     * Return the operations whitelist.
     *
     * @return array
     */
    public function getOperations()
    {
        return $this->_operations;
    }


    //
    // Private helper methods
    //
    /**
     * Check whether the requested operation is on
     * the list of permitted operations.
     *
     * @return bool
     */
    public function _checkOperationWhitelist()
    {
        // Only permit if the requested operation has been whitelisted.
        $router = $this->_request->getRouter(); /** @var \PKP\Core\PKPRouter|\PKP\Core\APIRouter $router */
        $requestedOperation = $router->getRequestedOp($this->_request);
        assert(!empty($requestedOperation));
        return in_array($requestedOperation, $this->_operations);
    }
}
